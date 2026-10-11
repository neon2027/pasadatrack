<?php

namespace Tests\Feature;

use App\Models\GoogleLinkChallenge;
use App\Models\User;
use App\Notifications\GoogleAccountLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class GoogleProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);
    }

    private function begin(string $email, string $subject = 'google-subject'): void
    {
        $this->get(route('google.redirect'))->assertRedirect();
        Socialite::fake('google', GoogleUser::fake(['id' => $subject, 'email' => $email, 'email_verified' => true]));
    }

    public function test_unknown_google_account_is_denied_without_provisioning(): void
    {
        $this->begin('unknown@gmail.com');
        $this->get(route('google.callback', ['code' => 'code']))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_suspended_google_account_cannot_sign_in_or_connect(): void
    {
        $user = User::factory()->create(['email' => 'suspended@gmail.com', 'account_status' => 'suspended']);
        $this->begin($user->email);
        $this->get(route('google.callback', ['code' => 'code']))->assertSessionHasErrors('google');
        $this->assertGuest();
        self::assertNull($user->fresh()->google_id);
    }

    /** @return array{User, GoogleLinkChallenge, string} */
    private function pendingLink(bool $twoFactor = false): array
    {
        Notification::fake();
        $factory = User::factory()->unverified();
        if ($twoFactor) {
            $factory = $factory->withTwoFactor();
        }
        $user = $factory->create(['email' => 'external@example.com']);
        $this->begin($user->email);
        $this->get(route('google.callback', ['code' => 'code']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/link-email')->where('mode', 'sent'));
        $token = null;
        Notification::assertSentTo($user, GoogleAccountLink::class, function (GoogleAccountLink $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        self::assertIsString($token);
        self::assertNull($user->fresh()->google_id);
        $this->assertGuest();

        return [$user, GoogleLinkChallenge::query()->firstOrFail(), $token];
    }

    public function test_email_link_connects_once_in_the_original_browser(): void
    {
        [$user, $challenge, $token] = $this->pendingLink();
        $url = URL::temporarySignedRoute('google.link.show', now()->addMinutes(15), ['challenge' => $challenge->id, 'token' => $token]);
        $this->get($url)->assertOk();
        self::assertNull($challenge->fresh()->consumed_at);
        $this->post(route('google.link.store', ['challenge' => $challenge->id, 'token' => $token]))->assertRedirect();
        $this->assertAuthenticatedAs($user);
        self::assertSame('google-subject', $user->fresh()->google_id);
        self::assertNotNull($user->fresh()->email_verified_at);
        self::assertNotNull($challenge->fresh()->consumed_at);
        $this->post(route('google.link.store', ['challenge' => $challenge->id, 'token' => $token]))->assertForbidden();
    }

    /** @return array<string, array{string}> */
    public static function invalidLinks(): array
    {
        return ['expired' => ['expired'], 'wrong browser' => ['browser'], 'wrong token' => ['token'], 'changed email' => ['email'], 'suspended' => ['suspended'], 'version changed' => ['version'], 'identity collision' => ['collision']];
    }

    #[DataProvider('invalidLinks')]
    public function test_invalid_link_never_connects_or_authenticates(string $case): void
    {
        [$user, $challenge, $token] = $this->pendingLink();
        match ($case) {
            'expired' => $challenge->update(['expires_at' => now()->subSecond()]),
            'browser' => session()->put('google.link_browser', 'other-browser'),
            'token' => $token = 'invalid-token',
            'email' => $user->forceFill(['email' => 'changed@example.com'])->save(),
            'suspended' => $user->forceFill(['account_status' => 'suspended'])->save(),
            'version' => $user->forceFill(['auth_version' => 1])->save(),
            'collision' => User::factory()->create(['google_id' => 'google-subject']),
        };
        $this->post(route('google.link.store', ['challenge' => $challenge->id, 'token' => $token]))->assertForbidden();
        $this->assertGuest();
        self::assertNull($user->fresh()->google_id);
    }

    public function test_email_link_retains_two_factor_challenge(): void
    {
        [$user, $challenge, $token] = $this->pendingLink(true);
        $this->post(route('google.link.store', ['challenge' => $challenge->id, 'token' => $token]))->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertRedirect()->assertSessionHas('auth.two_factor_verified', true);
        $this->assertAuthenticatedAs($user);
    }

    public function test_new_sign_in_invalidates_the_previous_verification_link(): void
    {
        [$user, $challenge, $token] = $this->pendingLink();
        $this->begin($user->email);
        $this->get(route('google.callback', ['code' => 'new-code']))->assertOk();
        $this->post(route('google.link.store', ['challenge' => $challenge->id, 'token' => $token]))->assertNotFound();
        $this->assertGuest();
    }

    public function test_verification_mail_failure_leaves_account_unlinked_and_shows_a_retry_message(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'external@example.com']);
        Notification::partialMock()->shouldReceive('send')->once()->andThrow(new TransportException('Test mail transport unavailable'));
        $this->begin($user->email);
        $this->get(route('google.callback', ['code' => 'code']))->assertRedirect(route('login'))->assertSessionHasErrors(['google' => 'We could not send the verification email. Please try again or contact an administrator.']);
        $this->assertGuest();
        self::assertNull($user->fresh()->google_id);
    }
}
