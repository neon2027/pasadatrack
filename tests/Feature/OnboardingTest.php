<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_workspace_visit_after_login_offers_the_tutorial(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->get(route('dashboard', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('onboarding.needsTutorial', true)
            );
    }

    public function test_guests_cannot_dismiss_an_accounts_tutorial(): void
    {
        $this->postJson(route('onboarding.dismiss'))->assertUnauthorized();
    }

    public function test_dismissing_saves_only_the_authenticated_users_preference(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->postJson(route('onboarding.dismiss'), [
            'user_id' => $otherUser->id,
            'onboarding_dismissed_at' => '2000-01-01 00:00:00',
        ])->assertNoContent();

        $this->assertSame(now()->timestamp, $user->refresh()->onboarding_dismissed_at->timestamp);
        $this->assertNull($otherUser->refresh()->onboarding_dismissed_at);

        $this->get(route('dashboard', $user->currentTeam))
            ->assertInertia(fn (Assert $page) => $page->where('onboarding.needsTutorial', false));
    }

    public function test_repeated_dismissals_preserve_the_first_saved_timestamp(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('onboarding.dismiss'))->assertNoContent();
        $firstDismissal = $user->refresh()->onboarding_dismissed_at->toISOString();

        $this->travel(1)->days();
        $this->postJson(route('onboarding.dismiss'))->assertNoContent();

        $this->assertSame($firstDismissal, $user->refresh()->onboarding_dismissed_at->toISOString());
    }

    public function test_team_members_can_save_their_own_tutorial_preference(): void
    {
        $user = User::factory()->create();
        $user->currentTeam->members()->updateExistingPivot($user->id, ['role' => TeamRole::Member->value]);

        $this->actingAs($user)->postJson(route('onboarding.dismiss'))->assertNoContent();

        $this->assertNotNull($user->refresh()->onboarding_dismissed_at);
    }

    public function test_users_without_a_selected_team_can_dismiss_the_guide(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['current_team_id' => null])->save();

        $this->actingAs($user)->postJson(route('onboarding.dismiss'))->assertNoContent();

        $this->assertNotNull($user->refresh()->onboarding_dismissed_at);
    }

    public function test_public_pages_do_not_offer_a_tutorial_to_guests(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('onboarding.needsTutorial', false));
    }
}
