<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FleetMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_fleet_map(): void
    {
        $team = Team::factory()->create();

        $response = $this->get(route('dashboard', ['current_team' => $team->slug]));

        $response->assertRedirect(route('login'));
    }

    public function test_users_cannot_view_another_teams_fleet_map(): void
    {
        config(['tracking.demo' => true]);
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $otherTeam->slug]));

        $response->assertForbidden();
    }

    #[TestWith(['local'])]
    #[TestWith(['testing'])]
    public function test_team_members_receive_the_synthetic_snapshot_without_creating_records(string $environment): void
    {
        config(['app.env' => $environment, 'tracking.demo' => true]);
        $this->travelTo(Carbon::parse('2026-10-09 12:00:00', 'Asia/Manila'));
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 0)
            ->has('tracking'),
        );
        $this->assertSame([
            'mode' => 'demo',
            'referenceDevice' => 'Teltonika FMC920',
            'center' => [14.5995, 120.9842],
            'timezone' => 'Asia/Manila',
            'freshnessSeconds' => 120,
            'vehicles' => [
                [
                    'id' => 'demo-01',
                    'plateNumber' => 'DEMO 001',
                    'routeName' => 'Sample Cubao - Quiapo',
                    'plannedDriver' => 'Sample Driver 01',
                    'actualDriver' => 'Sample Driver 01',
                    'isReliever' => false,
                    'latitude' => 14.6042,
                    'longitude' => 120.9891,
                    'speedKph' => 22,
                    'recordedAt' => '2026-10-09T03:59:30Z',
                ],
                [
                    'id' => 'demo-02',
                    'plateNumber' => 'DEMO 002',
                    'routeName' => 'Sample Divisoria - Taft',
                    'plannedDriver' => 'Sample Driver 02',
                    'actualDriver' => 'Sample Reliever 02',
                    'isReliever' => true,
                    'latitude' => 14.5983,
                    'longitude' => 120.9797,
                    'speedKph' => 0,
                    'recordedAt' => '2026-10-09T03:59:00Z',
                ],
                [
                    'id' => 'demo-03',
                    'plateNumber' => 'DEMO 003',
                    'routeName' => 'Sample Espana - Morayta',
                    'plannedDriver' => 'Sample Driver 03',
                    'actualDriver' => 'Sample Driver 03',
                    'isReliever' => false,
                    'latitude' => 14.6091,
                    'longitude' => 120.9954,
                    'speedKph' => null,
                    'recordedAt' => '2026-10-09T03:40:00Z',
                ],
                [
                    'id' => 'demo-04',
                    'plateNumber' => 'DEMO 004',
                    'routeName' => 'Sample Pandacan - Santa Cruz',
                    'plannedDriver' => 'Sample Driver 04',
                    'actualDriver' => null,
                    'isReliever' => false,
                    'latitude' => null,
                    'longitude' => null,
                    'speedKph' => null,
                    'recordedAt' => null,
                ],
            ],
        ], $response->inertiaProps('tracking'));

        foreach ([
            'drivers', 'jeepneys', 'routes', 'assignments', 'vehicle_unavailabilities',
            'driving_records', 'boundary_obligations', 'boundary_adjustments', 'payments',
            'payment_allocations', 'payment_reversals', 'biometric_devices', 'biometric_device_users',
            'biometric_events', 'attendance_sessions', 'attendance_corrections', 'audit_entries',
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_disabled_demo_returns_an_unconnected_snapshot(): void
    {
        config(['app.env' => 'testing', 'tracking.demo' => false]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('tracking', [
                'mode' => 'unconnected',
                'referenceDevice' => 'Teltonika FMC920',
                'center' => [14.5995, 120.9842],
                'timezone' => 'Asia/Manila',
                'freshnessSeconds' => 120,
                'vehicles' => [],
            ]),
        );
    }

    #[TestWith(['production'])]
    #[TestWith(['staging'])]
    public function test_demo_override_cannot_expose_samples_outside_local_or_testing(string $environment): void
    {
        config(['app.env' => $environment, 'tracking.demo' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('tracking.mode', 'unconnected')
            ->where('tracking.vehicles', []),
        );
    }
}
