<?php

namespace Tests\Feature;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamAccessRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, bool}> */
    public static function defaultPermissionMatrix(): array
    {
        $cases = [];
        $permissions = ['fleet:view', 'fleet:manage', 'scheduling:view', 'scheduling:manage', 'collections:view', 'collections:manage', 'attendance:view', 'attendance:manage', 'attendance-devices:manage', 'payment:reverse', 'reports:view', 'reports:export', 'audit:view', 'team:update', 'team:delete', 'member:add', 'member:update', 'member:remove', 'invitation:create', 'invitation:cancel'];
        foreach (['owner', 'admin', 'member'] as $role) {
            foreach ($permissions as $permission) {
                $cases[$role.' '.$permission] = [$role, $permission, $role === 'owner' || ($role === 'admin' && in_array($permission, ['team:update', 'invitation:create', 'invitation:cancel'], true))];
            }
        }

        return $cases;
    }

    #[DataProvider('defaultPermissionMatrix')]
    public function test_default_roles_preserve_their_permission_matrix(string $role, string $permission, bool $allowed): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => $role]);
        self::assertSame($allowed, $user->hasTeamPermission($team, TeamPermission::from($permission)));
    }

    /** @param array<string> $permissions */
    private function role(string $name, array $permissions): TeamAccessRole
    {
        return TeamAccessRole::factory()->create(['key' => strtolower(str_replace(' ', '-', $name)), 'name' => $name, 'permissions' => $permissions]);
    }

    private function join(User $user, Team $team, TeamAccessRole $role): void
    {
        $team->members()->attach($user, ['role' => TeamRole::Member->value, 'team_access_role_id' => $role->id]);
    }

    public function test_custom_viewer_can_read_fleet_but_cannot_edit_or_open_other_modules(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $this->join($user, $team, $this->role('Fleet Viewer', ['fleet:view']));
        $this->actingAs($user)->get(route('drivers.index', $team))->assertOk();
        $this->post(route('drivers.store', $team), [])->assertForbidden();
        $this->get(route('assignments.index', $team))->assertForbidden();
        $this->get(route('collections.index', $team))->assertForbidden();
        $this->get(route('attendance.index', $team))->assertForbidden();
        $this->get(route('reports.index', $team))->assertForbidden();
        $this->get(route('audit.index', $team))->assertForbidden();
    }

    public function test_custom_manager_can_create_fleet_records(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $this->join($user, $team, $this->role('Fleet Manager', ['fleet:view', 'fleet:manage']));
        $this->actingAs($user)->post(route('drivers.store', $team), ['code' => 'DRIVER-001', 'name' => 'Managed Driver', 'license_number' => 'LICENSE-001', 'phone' => '09123456789', 'is_active' => true])->assertRedirect();
        $this->assertDatabaseHas('drivers', ['team_id' => $team->id, 'name' => 'Managed Driver']);
    }

    public function test_report_viewing_does_not_grant_export_or_team_membership_bypass(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $this->join($user, $team, $this->role('Report Viewer', ['reports:view']));
        $this->actingAs($user)->get(route('reports.index', $team))->assertOk();
        $this->get(route('reports.export', ['current_team' => $team->slug, 'format' => 'csv']))->assertForbidden();
        $this->get(route('reports.index', $other))->assertForbidden();
    }

    public function test_delegated_manager_cannot_assign_stronger_roles_or_change_owner(): void
    {
        $actor = User::factory()->create();
        $member = User::factory()->create();
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $manager = $this->role('Member Manager', ['member:update', 'member:remove', 'invitation:create']);
        $stronger = $this->role('Fleet Controller', ['fleet:view', 'fleet:manage']);
        $this->join($actor, $team, $manager);
        $team->members()->attach($member, ['role' => 'member']);
        $this->actingAs($actor)->patch(route('teams.members.update', ['team' => $team->slug, 'user' => $member->id]), ['role' => $stronger->key])->assertForbidden();
        $this->patch(route('teams.members.update', ['team' => $team->slug, 'user' => $owner->id]), ['role' => 'member'])->assertForbidden();
        $this->delete(route('teams.members.destroy', ['team' => $team->slug, 'user' => $owner->id]))->assertForbidden();
        Notification::fake();
        $this->post(route('teams.invitations.store', $team), ['email' => 'invited@gmail.com', 'role' => $stronger->key])->assertForbidden();
        Notification::assertNothingSent();
        self::assertTrue($owner->fresh()->ownsTeam($team));
    }

    public function test_owner_can_assign_custom_roles_and_archive_preserves_existing_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => 'member']);
        $role = $this->role('Fleet Viewer', ['fleet:view']);
        $this->actingAs($owner)->patch(route('teams.members.update', ['team' => $team->slug, 'user' => $member->id]), ['role' => $role->key])->assertRedirect();
        self::assertTrue($member->hasTeamPermission($team, TeamPermission::ViewFleet));
        $role->update(['is_archived' => true]);
        $this->patch(route('teams.members.update', ['team' => $team->slug, 'user' => $member->id]), ['role' => $role->key])->assertSessionHasErrors('role');
        self::assertTrue($member->hasTeamPermission($team, TeamPermission::ViewFleet));
        $role->update(['permissions' => []]);
        self::assertFalse($member->hasTeamPermission($team, TeamPermission::ViewFleet));
    }

    public function test_basic_limit_counts_personal_team_but_not_other_memberships(): void
    {
        $user = User::factory()->create();
        $other = Team::factory()->create();
        $other->members()->attach($user, ['role' => 'member']);
        $this->actingAs($user)->post(route('teams.store'), ['name' => 'Extra Team'])->assertSessionHasErrors('name');
        self::assertSame(1, $user->ownedTeams()->count());
        self::assertSame(1, $user->fresh()->teamQuota()['limit']);
        $this->assertDatabaseMissing('teams', ['name' => 'Extra Team']);
    }

    public function test_plus_quota_and_soft_deleted_teams_release_capacity(): void
    {
        $user = User::factory()->create(['privilege_level_id' => 2]);
        $action = app(CreateTeam::class);
        $second = $action->handle($user, 'Second');
        $action->handle($user, 'Third');
        $this->actingAs($user)->post(route('teams.store'), ['name' => 'Fourth'])->assertSessionHasErrors('name');
        $second->delete();
        $this->post(route('teams.store'), ['name' => 'Replacement'])->assertRedirect();
        self::assertSame(3, $user->ownedTeams()->count());
    }

    public function test_platform_admin_has_no_automatic_team_access_or_quota_exemption(): void
    {
        $user = User::factory()->create(['platform_role' => 'super-admin']);
        $other = Team::factory()->create();
        $this->actingAs($user)->get(route('drivers.index', $other))->assertForbidden();
        $this->post(route('teams.store'), ['name' => 'Extra'])->assertSessionHasErrors('name');
    }

    /** @return array<string, array{string, string}> */
    public static function moduleReadEndpoints(): array
    {
        return ['drivers' => ['fleet:view', 'drivers.index'], 'jeepneys' => ['fleet:view', 'jeepneys.index'], 'routes' => ['fleet:view', 'routes.index'],
            'scheduling' => ['scheduling:view', 'assignments.index'], 'collections' => ['collections:view', 'collections.index'],
            'attendance' => ['attendance:view', 'attendance.index'], 'devices' => ['attendance-devices:manage', 'devices.index'],
            'reports' => ['reports:view', 'reports.index'], 'audit' => ['audit:view', 'audit.index']];
    }

    #[DataProvider('moduleReadEndpoints')]
    public function test_each_module_enforces_its_own_permission(string $permission, string $route): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $role = $this->role('Restricted role', [$permission]);
        $this->join($user, $team, $role);
        $this->actingAs($user)->get(route($route, $team))->assertOk();
        $role->update(['permissions' => []]);
        $this->get(route($route, $team))->assertForbidden();
    }

    public function test_admin_team_name_does_not_collide_with_platform_routes(): void
    {
        $user = User::factory()->create(['privilege_level_id' => 2]);
        $team = app(CreateTeam::class)->handle($user, 'Admin');
        self::assertSame('admin-team', $team->slug);
        $this->actingAs($user)->get(route('audit.index', $team))->assertOk();
        $this->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_member_management_does_not_expose_users_outside_the_team(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $target = ['team' => $owner->currentTeam->slug, 'user' => $stranger->id];
        $this->actingAs($owner)->patch(route('teams.members.update', $target), ['role' => 'member'])->assertNotFound();
        $this->delete(route('teams.members.destroy', $target))->assertNotFound();
        self::assertTrue($stranger->fresh()->ownsTeam($stranger->currentTeam));
    }
}
