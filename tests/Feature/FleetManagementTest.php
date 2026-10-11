<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Jeepney;
use App\Models\Fleet\Route as FleetRoute;
use App\Models\Scheduling\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FleetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_driver_without_setting_another_team(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('drivers.store', $user->currentTeam), [
            'code' => 'D-001', 'name' => 'Maria Santos', 'is_active' => true, 'team_id' => $other->current_team_id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('drivers.index', $user->currentTeam));

        $this->assertDatabaseHas('drivers', ['code' => 'D-001', 'name' => 'Maria Santos', 'team_id' => $user->current_team_id]);
        $this->assertDatabaseHas('audit_entries', ['action' => 'driver.created', 'actor_id' => $user->id]);
    }

    public function test_driver_codes_are_unique_within_the_team(): void
    {
        $user = User::factory()->create();
        Driver::factory()->create(['team_id' => $user->current_team_id, 'code' => 'D-001']);

        $this->actingAs($user)->post(route('drivers.store', $user->currentTeam), ['code' => 'D-001', 'name' => 'Duplicate', 'is_active' => true])
            ->assertSessionHasErrors(['code']);

        $this->assertDatabaseCount('drivers', 1);
    }

    public function test_archiving_a_driver_keeps_existing_assignments(): void
    {
        $user = User::factory()->create();
        $shift = Assignment::factory()->create(['team_id' => $user->current_team_id]);
        $driver = $shift->plannedDriver;

        $this->actingAs($user)->patch(route('drivers.update', [$user->currentTeam, $driver]), ['code' => $driver->code, 'name' => $driver->name, 'is_active' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($driver->fresh()->is_active);
        $this->assertModelExists($shift);
        $this->assertDatabaseHas('audit_entries', ['action' => 'driver.updated']);
    }

    public function test_driver_listing_filters_and_excludes_other_teams(): void
    {
        $user = User::factory()->create();
        $wanted = Driver::factory()->create(['team_id' => $user->current_team_id, 'name' => 'Maria Santos', 'is_active' => true]);
        Driver::factory()->create(['team_id' => $user->current_team_id, 'name' => 'Maria Reyes', 'is_active' => false]);
        Driver::factory()->create(['name' => 'Maria Other Team']);

        $this->actingAs($user)->get(route('drivers.index', [$user->currentTeam, 'search' => 'Maria', 'status' => 'active']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('fleet/masters')->has('records.data', 1)->where('records.data.0.id', $wanted->id));
    }

    public function test_cross_team_driver_update_is_hidden(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create(['name' => 'Original']);

        $this->actingAs($user)->patch(route('drivers.update', [$user->currentTeam, $driver]), ['code' => $driver->code, 'name' => 'Changed', 'is_active' => true])
            ->assertNotFound();

        $this->assertSame('Original', $driver->fresh()->name);
    }

    public function test_jeepney_capacity_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('jeepneys.store', $user->currentTeam), ['plate_number' => 'ABC123', 'capacity' => '-1', 'is_active' => true])
            ->assertSessionHasErrors(['capacity']);

        $this->assertDatabaseCount('jeepneys', 0);
    }

    public function test_routes_store_a_valid_default_boundary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('routes.store', $user->currentTeam), [
            'code' => 'RT01', 'name' => 'City loop', 'origin' => 'Terminal', 'destination' => 'Market',
            'default_boundary_amount' => '1200.50', 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('1200.50', FleetRoute::query()->sole()->default_boundary_amount);
    }

    public function test_schedule_cannot_use_foreign_or_inactive_entities(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create();
        $jeepney = Jeepney::factory()->create(['team_id' => $user->current_team_id, 'is_active' => false]);
        $route = FleetRoute::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)->post(route('assignments.store', $user->currentTeam), [
            'planned_driver_id' => $driver->id, 'jeepney_id' => $jeepney->id, 'route_id' => $route->id,
            'starts_at' => '2026-10-10T06:00', 'ends_at' => '2026-10-10T14:00',
        ])->assertSessionHasErrors(['planned_driver_id', 'jeepney_id']);

        $this->assertDatabaseCount('assignments', 0);
    }

    #[DataProvider('pages')]
    public function test_operational_pages_render_for_the_owner(string $route, string $component): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route($route, $user->currentTeam))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    }

    public static function pages(): array
    {
        return [
            'drivers' => ['drivers.index', 'fleet/masters'], 'jeepneys' => ['jeepneys.index', 'fleet/masters'],
            'routes' => ['routes.index', 'fleet/masters'], 'scheduling' => ['assignments.index', 'scheduling/index'],
            'collections' => ['collections.index', 'boundary/index'], 'attendance' => ['attendance.index', 'attendance/index'],
            'devices' => ['devices.index', 'attendance/devices'], 'reports' => ['reports.index', 'reports/index'],
            'audit' => ['audit.index', 'audit/index'],
        ];
    }

    public function test_guest_cannot_view_operational_records(): void
    {
        $user = User::factory()->create();

        $this->get(route('drivers.index', $user->currentTeam))->assertRedirect(route('login'));
    }

    public function test_member_cannot_view_new_modules_while_permission_setup_is_pending(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($member)->get(route('collections.index', $owner->currentTeam))->assertForbidden();
    }

    public function test_settings_redirects_to_profile_using_get(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings')->assertRedirect(route('profile.edit'));
    }
}
