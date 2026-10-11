<?php

namespace Tests\Feature;

use App\Actions\Admin\ManageUsers;
use App\Actions\Teams\CreateTeam;
use App\Enums\AccountStatus;
use App\Enums\PlatformRole;
use App\Models\PlatformAuditEntry;
use App\Models\PrivilegeLevel;
use App\Models\TeamAccessRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(PlatformRole $role = PlatformRole::SuperAdmin): User
    {
        $user = User::factory()->withTwoFactor()->create(['platform_role' => $role]);
        $this->actingAs($user)->withSession(['auth.version' => 0, 'auth.two_factor_verified' => true, 'auth.password_confirmed_at' => now()->timestamp]);

        return $user;
    }

    /** @return array<string, array{string}> */
    public static function adminPages(): array
    {
        return ['users' => ['admin.users.index'], 'roles' => ['admin.roles.index'], 'levels' => ['admin.levels.index'], 'audit' => ['admin.audit.index']];
    }

    #[DataProvider('adminPages')]
    public function test_super_admin_can_open_administration_pages(string $route): void
    {
        $this->administrator();
        $this->get(route($route))->assertOk();
    }

    #[DataProvider('adminPages')]
    public function test_regular_users_cannot_open_administration_pages(string $route): void
    {
        $this->actingAs(User::factory()->create())->get(route($route))->assertForbidden();
    }

    public function test_guests_must_sign_in(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_admin_cannot_manage_catalog_or_privileged_accounts(): void
    {
        $admin = $this->administrator(PlatformRole::Admin);
        $super = User::factory()->withTwoFactor()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $this->get(route('admin.users.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('users.total', 0));
        $this->get(route('admin.roles.index'))->assertForbidden();
        $this->patch(route('admin.users.update', $super), ['name' => 'Changed', 'account_status' => 'active', 'privilege_level_id' => 1, 'reason' => 'Attempt'])->assertForbidden();
        self::assertSame(PlatformRole::SuperAdmin, $super->fresh()->platform_role);
        self::assertSame(PlatformRole::Admin, $admin->fresh()->platform_role);
    }

    public function test_admin_can_provision_ordinary_user_with_level_and_personal_team(): void
    {
        $this->administrator(PlatformRole::Admin);
        $this->post(route('admin.users.store'), ['name' => 'Fleet Operator', 'email' => ' OPERATOR@gmail.com ', 'privilege_level_id' => 2, 'reason' => 'Approved operator'])->assertRedirect(route('admin.users.index'));
        $user = User::query()->where('email', 'operator@gmail.com')->firstOrFail();
        self::assertSame(PlatformRole::User, $user->platform_role);
        self::assertTrue($user->isActive());
        self::assertNull($user->email_verified_at);
        self::assertNull($user->google_id);
        self::assertSame(2, $user->privilege_level_id);
        self::assertSame(1, $user->ownedTeams()->count());
        $this->assertDatabaseHas('platform_audit_entries', ['action' => 'user.provisioned', 'subject_id' => $user->id, 'reason' => 'Approved operator']);
        self::assertArrayNotHasKey('password', PlatformAuditEntry::query()->firstOrFail()->after);
    }

    public function test_admin_cannot_inject_an_administrator_role(): void
    {
        $this->administrator(PlatformRole::Admin);
        $this->post(route('admin.users.store'), ['name' => 'Escalation', 'email' => 'escalation@gmail.com', 'privilege_level_id' => 1, 'platform_role' => 'super-admin', 'reason' => 'Attempt'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'escalation@gmail.com']);
    }

    public function test_admin_cannot_promote_an_ordinary_user(): void
    {
        $this->administrator(PlatformRole::Admin);
        $user = User::factory()->create();
        $this->patch(route('admin.users.update', $user), ['name' => $user->name, 'account_status' => 'active', 'privilege_level_id' => 1, 'platform_role' => 'admin', 'reason' => 'Attempt'])->assertForbidden();
        self::assertSame(PlatformRole::User, $user->fresh()->platform_role);
    }

    public function test_administrator_requires_confirmed_two_factor_and_a_challenged_session(): void
    {
        $user = User::factory()->create(['platform_role' => PlatformRole::Admin]);
        $this->actingAs($user)->get(route('admin.users.index'))->assertRedirect(route('security.edit'));
        $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sensitive_changes_require_google_confirmation_within_five_minutes(): void
    {
        $this->administrator();
        $this->withSession(['auth.password_confirmed_at' => now()->subMinutes(6)->timestamp]);
        $this->post(route('admin.users.store'), ['name' => 'User', 'email' => 'blocked@gmail.com', 'privilege_level_id' => 1, 'reason' => 'Requested'])->assertRedirect(route('password.confirm'));
        $this->assertDatabaseMissing('users', ['email' => 'blocked@gmail.com']);
    }

    public function test_admin_cannot_disable_two_factor(): void
    {
        $user = $this->administrator();
        $this->delete(route('two-factor.disable'))->assertForbidden();
        self::assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_suspension_revokes_sessions_and_preserves_teams(): void
    {
        $this->administrator();
        $target = User::factory()->create();
        $team = $target->current_team_id;
        DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        $this->patch(route('admin.users.update', $target), ['name' => $target->name, 'account_status' => 'suspended', 'privilege_level_id' => 1, 'reason' => 'Access removed'])->assertRedirect();
        self::assertSame(AccountStatus::Suspended, $target->fresh()->account_status);
        self::assertSame(1, $target->fresh()->auth_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('teams', ['id' => $team]);
        $this->actingAs($target)->withSession(['auth.version' => 0])->get(route('teams.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_suspended_pending_two_factor_user_cannot_complete_challenge(): void
    {
        $this->administrator();
        $target = User::factory()->withTwoFactor()->create();
        app(ManageUsers::class)->update(Auth::user(), $target, ['account_status' => 'suspended', 'reason' => 'Access removed']);
        Auth::logout();
        $this->withSession(['login.id' => $target->id, 'login.version' => 0])->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertRedirect(route('login'));
        $this->assertGuest();
        self::assertContains('recovery-code-1', $target->fresh()->recoveryCodes());
    }

    public function test_role_change_revokes_the_previous_session(): void
    {
        $actor = $this->administrator();
        $target = User::factory()->withTwoFactor()->create();
        app(ManageUsers::class)->update($actor, $target, ['platform_role' => 'admin', 'reason' => 'Approved administrator']);
        $this->actingAs($target)->withSession(['auth.version' => 0])->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_last_active_super_admin_cannot_be_demoted_suspended_or_deleted(): void
    {
        $user = $this->administrator();
        $payload = ['name' => $user->name, 'account_status' => 'active', 'privilege_level_id' => 1, 'platform_role' => 'user', 'reason' => 'Demotion'];
        $this->patch(route('admin.users.update', $user), $payload)->assertSessionHasErrors('platform_role');
        $this->patch(route('admin.users.update', $user), array_replace($payload, ['account_status' => 'suspended', 'platform_role' => 'super-admin']))->assertSessionHasErrors('account_status');
        $this->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])->assertSessionHasErrors('platform_role');
        self::assertTrue($user->fresh()->isActive());
        self::assertSame(PlatformRole::SuperAdmin, $user->fresh()->platform_role);
    }

    public function test_super_admin_can_change_levels_without_removing_existing_teams(): void
    {
        $this->administrator();
        $level = PrivilegeLevel::query()->findOrFail(2);
        $user = User::factory()->create(['privilege_level_id' => 2]);
        app(CreateTeam::class)->handle($user, 'Second team');
        $this->patch(route('admin.levels.update', $level), ['name' => 'Plus', 'team_limit' => 1, 'reason' => 'Reduced entitlement'])->assertRedirect();
        self::assertSame(2, $user->ownedTeams()->count());
        self::assertFalse($user->fresh()->teamQuota()['canCreate']);
        $this->patch(route('admin.levels.update', $level), ['name' => 'Plus', 'team_limit' => 0, 'reason' => 'Invalid'])->assertSessionHasErrors('team_limit');
    }

    public function test_role_creation_validates_dependencies_and_owner_is_protected(): void
    {
        $this->administrator();
        $this->post(route('admin.roles.store'), ['name' => 'Fleet Manager', 'permissions' => ['fleet:manage'], 'reason' => 'New role'])->assertSessionHasErrors('permissions');
        $this->post(route('admin.roles.store'), ['name' => 'Fleet Manager', 'permissions' => ['fleet:view', 'fleet:manage'], 'reason' => 'New role'])->assertRedirect();
        $this->assertDatabaseHas('team_access_roles', ['name' => 'Fleet Manager', 'is_owner' => false]);
        $owner = TeamAccessRole::query()->where('key', 'owner')->firstOrFail();
        $this->patch(route('admin.roles.update', $owner), ['name' => 'Owner', 'permissions' => [], 'reason' => 'Attempt'])->assertForbidden();
        $this->post(route('admin.roles.store'), ['name' => 'Unknown permission', 'permissions' => ['platform:super-admin'], 'reason' => 'Attempt'])->assertSessionHasErrors('permissions.0');
    }

    public function test_console_command_bootstraps_a_super_admin_without_bypassing_authentication(): void
    {
        $this->artisan('app:admin', ['email' => 'bootstrap@gmail.com', '--name' => 'Bootstrap Admin', '--no-interaction' => true])->assertSuccessful();
        $user = User::query()->where('email', 'bootstrap@gmail.com')->firstOrFail();
        self::assertSame(PlatformRole::SuperAdmin, $user->platform_role);
        self::assertNull($user->google_id);
        self::assertNull($user->email_verified_at);
        self::assertFalse($user->hasEnabledTwoFactorAuthentication());
        self::assertSame(1, $user->ownedTeams()->count());
        $this->assertDatabaseHas('platform_audit_entries', ['source' => 'console', 'action' => 'user.provisioned']);
    }

    public function test_console_promotion_requires_explicit_flags_and_preserves_identity_and_memberships(): void
    {
        $user = User::factory()->create(['email' => 'existing@gmail.com', 'google_id' => 'stable-google']);
        $teamId = $user->current_team_id;
        $this->artisan('app:admin', ['email' => $user->email, '--no-interaction' => true])->assertFailed();
        $this->artisan('app:admin', ['email' => $user->email, '--promote' => true, '--no-interaction' => true])->assertFailed();
        $this->artisan('app:admin', ['email' => $user->email, '--promote' => true, '--force' => true, '--role' => 'admin', '--no-interaction' => true])->assertSuccessful();
        self::assertSame('stable-google', $user->fresh()->google_id);
        self::assertSame($teamId, $user->fresh()->current_team_id);
        self::assertSame(PlatformRole::Admin, $user->fresh()->platform_role);
        $this->assertDatabaseCount('teams', 1);
    }

    public function test_command_rejects_invalid_role_and_email(): void
    {
        $this->artisan('app:admin', ['email' => 'bad-email', '--name' => 'Bad', '--role' => 'user'])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_profile_payload_cannot_escalate_privileges(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Operator', 'platform_role' => 'super-admin', 'account_status' => 'active', 'privilege_level_id' => 3])->assertRedirect();
        self::assertSame(PlatformRole::User, $user->fresh()->platform_role);
        self::assertSame(1, $user->fresh()->privilege_level_id);
    }

    public function test_platform_audit_cannot_be_modified(): void
    {
        $this->administrator();
        $this->post(route('admin.users.store'), ['name' => 'Operator', 'email' => 'operator@gmail.com', 'privilege_level_id' => 1, 'reason' => 'Approved'])->assertRedirect();
        $entry = PlatformAuditEntry::query()->firstOrFail();
        $this->expectException(\LogicException::class);
        $entry->update(['reason' => 'Tampered']);
    }

    public function test_successful_two_factor_setup_allows_administrator_session_without_another_login(): void
    {
        $user = User::factory()->create(['platform_role' => PlatformRole::Admin]);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $this->post(route('two-factor.enable'))->assertRedirect();
        $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
        $this->post(route('two-factor.confirm'), ['code' => $code])->assertRedirect()->assertSessionHas('auth.two_factor_verified', true);
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_another_super_admin_can_suspend_an_administrator_and_reactivate_them(): void
    {
        $actor = $this->administrator();
        $target = User::factory()->withTwoFactor()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $payload = ['name' => $target->name, 'platform_role' => 'super-admin', 'privilege_level_id' => 1, 'account_status' => 'suspended', 'reason' => 'Access review'];
        $this->patch(route('admin.users.update', $target), $payload)->assertRedirect();
        self::assertFalse($target->fresh()->isActive());
        $this->patch(route('admin.users.update', $target), array_replace($payload, ['account_status' => 'active']))->assertRedirect();
        self::assertTrue($target->fresh()->isActive());
        self::assertSame(2, $target->fresh()->auth_version);
        self::assertTrue($actor->fresh()->isActive());
    }

    public function test_google_confirmation_returns_to_administration_and_rejects_external_return_url(): void
    {
        $this->administrator();
        $this->get(route('password.confirm', ['return_to' => '/admin/roles']))->assertOk()->assertSessionHas('url.intended', url('/admin/roles'));
        session()->forget('url.intended');
        $this->get(route('password.confirm', ['return_to' => 'https://attacker.example']))->assertOk()->assertSessionHas('url.intended', route('profile.edit'));
    }

    public function test_console_can_require_and_accept_interactive_promotion_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'promote@gmail.com']);
        $this->artisan('app:admin', ['email' => $user->email, '--promote' => true])->expectsConfirmation('Promote promote@gmail.com to super-admin?', 'no')->assertFailed();
        $this->artisan('app:admin', ['email' => $user->email, '--promote' => true])->expectsConfirmation('Promote promote@gmail.com to super-admin?', 'yes')->assertSuccessful();
        self::assertSame(PlatformRole::SuperAdmin, $user->fresh()->platform_role);
    }
}
