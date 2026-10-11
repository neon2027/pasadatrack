<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\GoogleAccountLink;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);
    }

    public function test_login_page_offers_google_sign_in(): void
    {
        $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')->where('googleConfigured', true)->missing('canResetPassword'));
    }

    public function test_google_authorization_uses_session_state_and_pkce(): void
    {
        $response = $this->get(route('google.redirect'));
        $response->assertRedirect()->assertSessionHas('state')->assertSessionHas('code_verifier');

        $url = $response->headers->get('Location');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        self::assertSame(session('state'), $query['state']);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame('openid profile email', $query['scope']);
        self::assertSame('select_account', $query['prompt']);
    }

    public function test_provisioned_google_user_gets_verified_and_keeps_personal_team(): void
    {
        User::factory()->unverified()->create(['email' => 'new@gmail.com']);
        $this->beginSignIn(GoogleUser::fake(['id' => 'google-123', 'email' => 'new@gmail.com', 'email_verified' => true]));
        $response = $this->get(route('google.callback', ['code' => 'authorization-code']));

        $user = User::query()->where('google_id', 'google-123')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
        $response->assertSessionHas('auth.password_confirmed_at');
        $response->assertSessionMissing('google.login');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('teams', 1);
        self::assertNotNull($user->email_verified_at);
        self::assertTrue($user->currentTeam->is_personal);
        self::assertTrue($user->ownsTeam($user->currentTeam));
        self::assertArrayNotHasKey('google_id', $user->toArray());
        self::assertArrayNotHasKey('token', $user->toArray());
    }

    public function test_legacy_gmail_account_is_linked_without_losing_team_memberships(): void
    {
        $user = User::factory()->create(['email' => 'owner@gmail.com']);
        $teamId = $user->current_team_id;
        $this->beginSignIn(GoogleUser::fake(['id' => 'google-existing', 'email' => $user->email, 'email_verified' => true]));

        $this->get(route('google.callback', ['code' => 'code']))->assertRedirect();

        $this->assertAuthenticatedAs($user);
        self::assertSame('google-existing', $user->fresh()->google_id);
        self::assertSame($teamId, $user->fresh()->current_team_id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('teams', 1);
    }

    public function test_workspace_email_is_linked_using_the_verified_hosted_domain(): void
    {
        $user = User::factory()->create(['email' => 'owner@fleet.example']);
        $this->beginSignIn(GoogleUser::fake(['id' => 'workspace-id', 'email' => $user->email, 'email_verified' => true, 'hd' => 'fleet.example']));

        $this->get(route('google.callback', ['code' => 'code']))->assertRedirect();

        $this->assertAuthenticatedAs($user);
        self::assertSame('workspace-id', $user->fresh()->google_id);
    }

    public function test_linked_google_id_remains_the_identity_when_google_email_changes(): void
    {
        $user = $this->linkedUser();
        $this->beginSignIn(GoogleUser::fake(['id' => $user->google_id, 'email' => 'renamed@gmail.com', 'email_verified' => true]));

        $this->get(route('google.callback', ['code' => 'code']))->assertRedirect();

        $this->assertAuthenticatedAs($user);
        self::assertSame('renamed@gmail.com', $user->fresh()->email);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_account_cannot_take_over_an_email_linked_to_another_google_id(): void
    {
        $user = $this->linkedUser();
        $this->beginSignIn(GoogleUser::fake(['id' => 'attacker-google-id', 'email' => $user->email, 'email_verified' => true]));

        $this->get(route('google.callback', ['code' => 'code']))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertGuest();
        self::assertSame('google-linked', $user->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('untrustedProfiles')]
    public function test_untrusted_google_profiles_cannot_claim_an_existing_account(array $profile): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => $profile['email'] ?: 'owner@gmail.com']);
        $this->beginSignIn(GoogleUser::fake($profile));

        $response = $this->get(route('google.callback', ['code' => 'code']));
        if (($profile['email'] ?? '') === 'owner@example.com' && ($profile['email_verified'] ?? false) === true) {
            $response->assertOk();
            Notification::assertSentTo($user, GoogleAccountLink::class);
        } else {
            $response->assertRedirect(route('login'))->assertSessionHasErrors('google');
        }

        $this->assertGuest();
        self::assertNull($user->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function untrustedProfiles(): array
    {
        return [
            'unverified email' => [['id' => 'google-untrusted', 'email' => 'owner@gmail.com', 'email_verified' => false]],
            'missing verification claim' => [['id' => 'google-untrusted', 'email' => 'owner@gmail.com']],
            'third party email' => [['id' => 'google-untrusted', 'email' => 'owner@example.com', 'email_verified' => true]],
            'missing subject' => [['id' => '', 'email' => 'owner@gmail.com', 'email_verified' => true]],
            'missing email' => [['id' => 'google-untrusted', 'email' => '', 'email_verified' => true]],
        ];
    }

    public function test_another_accounts_email_cannot_replace_a_linked_users_email(): void
    {
        $user = $this->linkedUser();
        $other = User::factory()->create(['email' => 'other@gmail.com']);
        $this->beginSignIn(GoogleUser::fake(['id' => $user->google_id, 'email' => $other->email, 'email_verified' => true]));

        $this->get(route('google.callback', ['code' => 'code']))->assertSessionHasErrors('google');

        $this->assertGuest();
        self::assertSame('linked@gmail.com', $user->fresh()->email);
        self::assertNull($other->fresh()->google_id);
    }

    public function test_invalid_oauth_state_never_authenticates_or_creates_an_account(): void
    {
        $this->get(route('google.redirect'))->assertRedirect();

        $this->get(route('google.callback', ['state' => 'incorrect-state', 'code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google')->assertSessionMissing('state');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_real_provider_callback_validates_state_and_uses_server_side_userinfo(): void
    {
        User::factory()->unverified()->create(['email' => 'real@gmail.com']);
        $this->get(route('google.redirect'))->assertRedirect();
        $state = session('state');
        $handler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'test-access-token', 'expires_in' => 3600])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['sub' => 'real-provider-sub', 'name' => 'Google User', 'email' => 'real@gmail.com', 'email_verified' => true])),
        ]);
        /** @var GoogleProvider $provider */
        $provider = Socialite::driver('google');
        $provider->setHttpClient(new Client(['handler' => HandlerStack::create($handler)]));

        $this->get(route('google.callback', ['state' => $state, 'code' => 'code']))->assertRedirect()->assertSessionMissing('state');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['google_id' => 'real-provider-sub', 'email' => 'real@gmail.com']);
        self::assertCount(0, $handler);
    }

    public function test_google_cancellation_does_not_create_an_account(): void
    {
        $this->get(route('google.redirect'))->assertRedirect();

        $this->get(route('google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google')->assertSessionMissing('google.login');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_or_replayed_callbacks_are_rejected(): void
    {
        $this->beginSignIn(GoogleUser::fake(['email' => 'expired@gmail.com', 'email_verified' => true]));
        $this->travel(11)->minutes();

        $this->get(route('google.callback', ['code' => 'code']))->assertSessionHasErrors('google');
        $this->get(route('google.callback', ['code' => 'code']))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_two_factor_authentication_is_required_after_google_sign_in(): void
    {
        $user = User::factory()->withTwoFactor()->create(['email' => 'twofactor@gmail.com']);
        $this->beginSignIn(GoogleUser::fake(['id' => 'two-factor-google', 'email' => $user->email, 'email_verified' => true]));

        $this->get(route('google.callback', ['code' => 'code']))
            ->assertRedirect(route('two-factor.login'))->assertSessionHas('login.id', $user->id)->assertSessionMissing('auth.password_confirmed_at');
        $this->assertGuest();

        $recoveryCode = json_decode(decrypt($user->two_factor_recovery_codes), true)[0];
        $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
            ->assertRedirect()->assertSessionHas('auth.password_confirmed_at')->assertSessionMissing('google.two_factor_pending');
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_confirmation_cannot_switch_the_logged_in_account(): void
    {
        $user = $this->linkedUser();
        $this->actingAs($user);
        $this->beginSignIn(GoogleUser::fake(['id' => 'different-google', 'email' => 'different@gmail.com', 'email_verified' => true]));

        $this->get(route('google.callback', ['code' => 'code']))->assertRedirect(route('password.confirm'))->assertSessionHasErrors('google');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        self::assertNull(session('auth.password_confirmed_at'));
    }

    public function test_same_google_account_confirms_identity_and_returns_to_intended_settings(): void
    {
        $user = $this->linkedUser();
        $this->actingAs($user)->withSession(['url.intended' => route('security.edit')]);
        $this->beginSignIn(GoogleUser::fake(['id' => $user->google_id, 'email' => $user->email, 'email_verified' => true]));

        $this->get(route('google.callback', ['code' => 'code']))
            ->assertRedirect(route('security.edit'))->assertSessionHas('auth.password_confirmed_at');

        $this->assertAuthenticatedAs($user);
    }

    public function test_missing_google_credentials_show_unavailable_sign_in_without_remote_requests(): void
    {
        config(['services.google.client_secret' => null]);

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('googleConfigured', false));
        $this->get(route('google.redirect'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_authorization_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get(route('google.redirect'))->assertRedirect();
        }

        $this->get(route('google.redirect'))->assertTooManyRequests();
    }

    public function test_google_login_preserves_email_matched_team_invitations(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invited@gmail.com', 'invited_by' => $owner->id, 'role' => TeamRole::Member]);
        User::factory()->unverified()->create(['email' => 'invited@gmail.com']);
        $this->beginSignIn(GoogleUser::fake(['id' => 'invited-google', 'email' => $invitation->email, 'email_verified' => true]));
        $this->get(route('google.callback', ['code' => 'code']))->assertRedirect();

        $this->post(route('invitations.accept', $invitation))->assertRedirect();

        $user = User::query()->where('google_id', 'invited-google')->firstOrFail();
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $user->id, 'role' => TeamRole::Member->value]);
        self::assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_provisioned_third_party_google_email_requires_verification_before_linking(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'new@example.com']);
        $this->beginSignIn(GoogleUser::fake(['id' => 'external-email-id', 'email' => $user->email, 'email_verified' => true]));
        $this->get(route('google.callback', ['code' => 'code']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/link-email')->where('mode', 'sent'));
        $this->assertGuest();
        self::assertNull($user->fresh()->google_id);
        Notification::assertSentTo($user, GoogleAccountLink::class);
    }

    public function test_google_email_change_to_an_external_mailbox_requires_fresh_verification(): void
    {
        Notification::fake();
        $user = $this->linkedUser();
        $this->beginSignIn(GoogleUser::fake(['id' => $user->google_id, 'email' => 'changed@example.com', 'email_verified' => true]));

        $this->get(route('google.callback', ['code' => 'code']))->assertRedirect();

        self::assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_current_legacy_gmail_session_can_connect_its_own_google_account(): void
    {
        $user = User::factory()->create(['email' => 'legacy@gmail.com']);
        $this->actingAs($user);
        $this->beginSignIn(GoogleUser::fake(['id' => 'legacy-google-id', 'email' => $user->email, 'email_verified' => true]));

        $this->get(route('google.callback', ['code' => 'code']))->assertRedirect()->assertSessionHas('auth.password_confirmed_at');

        $this->assertAuthenticatedAs($user);
        self::assertSame('legacy-google-id', $user->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_connection_failure_does_not_authenticate_or_create_a_user(): void
    {
        $this->get(route('google.redirect'))->assertRedirect();
        Socialite::fake('google', fn () => throw new ConnectException(
            'Unavailable', new Request('POST', 'https://www.googleapis.com/oauth2/v4/token')
        ));

        $this->get(route('google.callback', ['code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google')->assertSessionMissing('google.login');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    private function beginSignIn(GoogleUser $googleUser): void
    {
        $this->get(route('google.redirect'))->assertRedirect();
        Socialite::fake('google', $googleUser);
    }

    private function linkedUser(): User
    {
        $user = User::factory()->create(['email' => 'linked@gmail.com']);
        $user->forceFill(['google_id' => 'google-linked'])->save();

        return $user;
    }
}
