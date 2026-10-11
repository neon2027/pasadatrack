<?php

namespace Tests\Feature;

use App\Actions\Boundary\ManageCollections;
use App\Actions\Scheduling\ManageAssignments;
use App\Data\Boundary\BoundaryBalance;
use App\Enums\Scheduling\AssignmentStatus;
use App\Enums\TeamRole;
use App\Models\Attendance\AttendanceSession;
use App\Models\Audit\AuditEntry;
use App\Models\Boundary\BoundaryObligation;
use App\Models\Boundary\Payment;
use App\Models\Boundary\PaymentAllocation;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Jeepney;
use App\Models\Fleet\Route as FleetRoute;
use App\Models\Scheduling\Assignment;
use App\Models\Scheduling\VehicleUnavailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_shift_with_utc_times_and_manila_operational_date(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $driver = Driver::factory()->create(['team_id' => $team->id]);
        $jeepney = Jeepney::factory()->create(['team_id' => $team->id]);
        $route = FleetRoute::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)->post(route('assignments.store', $team), [
            'planned_driver_id' => $driver->id, 'jeepney_id' => $jeepney->id, 'route_id' => $route->id,
            'starts_at' => '2026-10-10T06:00', 'ends_at' => '2026-10-10T14:00', 'status' => 'confirmed',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assignments', [
            'team_id' => $team->id, 'starts_at' => '2026-10-09 22:00:00', 'ends_at' => '2026-10-10 06:00:00',
            'status' => 'planned',
        ]);
        $this->assertSame('2026-10-10', Assignment::query()->sole()->operational_date->toDateString());
        $this->assertDatabaseCount('boundary_obligations', 0);
        $this->assertDatabaseHas('audit_entries', ['action' => 'assignment.created', 'actor_id' => $user->id]);
    }

    #[DataProvider('overlaps')]
    public function test_rejects_overlapping_driver_or_jeepney_assignments(string $field): void
    {
        $user = User::factory()->create();
        $shift = $this->shift($user);
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id]);
        $jeepney = Jeepney::factory()->create(['team_id' => $user->current_team_id]);
        $payload = [
            'planned_driver_id' => $field === 'planned_driver_id' ? $shift->planned_driver_id : $driver->id,
            'jeepney_id' => $field === 'jeepney_id' ? $shift->jeepney_id : $jeepney->id,
            'route_id' => $shift->route_id, 'starts_at' => '2026-10-10T07:00', 'ends_at' => '2026-10-10T09:00',
        ];

        $this->actingAs($user)->post(route('assignments.store', $user->currentTeam), $payload)
            ->assertSessionHasErrors([$field]);

        $this->assertDatabaseCount('assignments', 1);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public static function overlaps(): array
    {
        return ['driver' => ['planned_driver_id'], 'vehicle' => ['jeepney_id']];
    }

    public function test_allows_adjacent_shifts_without_an_overlap(): void
    {
        $user = User::factory()->create();
        $shift = $this->shift($user);

        $this->actingAs($user)->post(route('assignments.store', $user->currentTeam), [
            'planned_driver_id' => $shift->planned_driver_id, 'jeepney_id' => $shift->jeepney_id, 'route_id' => $shift->route_id,
            'starts_at' => '2026-10-10T14:00', 'ends_at' => '2026-10-10T22:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('assignments', 2);
    }

    public function test_confirming_a_substitute_creates_one_immutable_boundary_snapshot(): void
    {
        $user = User::factory()->create();
        $shift = $this->shift($user);
        $substitute = Driver::factory()->create(['team_id' => $user->current_team_id]);
        $shift->route->update(['default_boundary_amount' => '1200.35']);

        $this->actingAs($user)->post(route('assignments.confirm', [$user->currentTeam, $shift]), ['actual_driver_id' => $substitute->id, 'expected_boundary_amount' => '1200.35'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('driving_records', ['assignment_id' => $shift->id, 'actual_driver_id' => $substitute->id, 'boundary_amount' => '1200.35']);
        $this->assertDatabaseHas('boundary_obligations', ['driver_id' => $substitute->id, 'original_amount' => '1200.35']);
        $this->assertSame(AssignmentStatus::Confirmed, $shift->fresh()->status);
    }

    public function test_double_confirmation_does_not_duplicate_financial_records(): void
    {
        $user = User::factory()->create();
        $shift = $this->confirmedShift($user);

        $this->actingAs($user)->post(route('assignments.confirm', [$user->currentTeam, $shift]), ['actual_driver_id' => $shift->planned_driver_id, 'expected_boundary_amount' => '1000.00'])
            ->assertSessionHasErrors(['status']);

        $this->assertDatabaseCount('driving_records', 1);
        $this->assertDatabaseCount('boundary_obligations', 1);
    }

    public function test_cancelled_shifts_cannot_create_a_boundary(): void
    {
        $user = User::factory()->create();
        $shift = $this->shift($user, ['status' => AssignmentStatus::Cancelled]);

        $this->actingAs($user)->post(route('assignments.confirm', [$user->currentTeam, $shift]), ['actual_driver_id' => $shift->planned_driver_id, 'expected_boundary_amount' => $shift->route->default_boundary_amount])
            ->assertSessionHasErrors(['status']);

        $this->assertDatabaseCount('driving_records', 0);
        $this->assertDatabaseCount('boundary_obligations', 0);
    }

    public function test_cancel_preserves_the_shift_and_records_the_reason(): void
    {
        $user = User::factory()->create();
        $shift = $this->shift($user);

        $this->actingAs($user)->post(route('assignments.cancel', [$user->currentTeam, $shift]), ['reason' => 'Vehicle repair'])
            ->assertSessionHasNoErrors();

        $this->assertSame(AssignmentStatus::Cancelled, $shift->fresh()->status);
        $this->assertDatabaseHas('audit_entries', ['action' => 'assignment.cancelled', 'reason' => 'Vehicle repair']);
        $this->assertDatabaseCount('boundary_obligations', 0);
    }

    public function test_vehicle_unavailability_prevents_scheduling(): void
    {
        $user = User::factory()->create();
        $shift = $this->shift($user, ['status' => AssignmentStatus::Cancelled]);
        VehicleUnavailability::query()->create(['team_id' => $shift->team_id, 'jeepney_id' => $shift->jeepney_id, 'starts_at' => '2026-10-09 22:00:00', 'ends_at' => '2026-10-10 06:00:00', 'reason' => 'Maintenance']);

        $this->actingAs($user)->post(route('assignments.store', $user->currentTeam), [
            'planned_driver_id' => $shift->planned_driver_id, 'jeepney_id' => $shift->jeepney_id, 'route_id' => $shift->route_id,
            'starts_at' => '2026-10-10T06:00', 'ends_at' => '2026-10-10T14:00',
        ])->assertSessionHasErrors(['jeepney_id']);

        $this->assertDatabaseCount('assignments', 1);
    }

    public function test_unavailability_rejects_existing_shifts(): void
    {
        $user = User::factory()->create();
        $shift = $this->shift($user);

        $this->actingAs($user)->post(route('unavailabilities.store', $user->currentTeam), [
            'jeepney_id' => $shift->jeepney_id, 'starts_at' => '2026-10-10T06:00', 'ends_at' => '2026-10-10T08:00', 'reason' => 'Repair',
        ])->assertSessionHasErrors(['jeepney_id']);

        $this->assertDatabaseCount('vehicle_unavailabilities', 0);
    }

    public function test_payments_keep_exact_centavo_balances(): void
    {
        $this->travelTo('2026-10-10 12:00:00');
        $user = User::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)->post(route('collections.store', $user->currentTeam), [
            'driver_id' => $driver->id, 'amount' => '0.10', 'receipt_number' => 'R-001', 'collected_at' => '2026-10-10T19:00', 'received_by' => 999,
        ])->assertSessionHasNoErrors();

        $payment = Payment::query()->sole();
        $this->assertSame('0.10', app(BoundaryBalance::class)->available($payment));
        $this->assertSame($user->id, $payment->received_by);
        $this->assertDatabaseHas('audit_entries', ['action' => 'payment.collected']);
    }

    public function test_partial_allocation_keeps_unallocated_receipt_credit(): void
    {
        $user = User::factory()->create();
        $shift = $this->confirmedShift($user);
        $payment = $this->payment($user, $shift->planned_driver_id, '1000.10');
        $obligation = BoundaryObligation::query()->sole();

        $this->actingAs($user)->post(route('collections.allocate', [$user->currentTeam, $payment]), ['boundary_obligation_id' => $obligation->id, 'amount' => '400.07'])
            ->assertSessionHasNoErrors();

        $this->assertSame('600.03', app(BoundaryBalance::class)->available($payment->fresh()));
        $this->assertSame('599.93', app(BoundaryBalance::class)->obligation($obligation)['outstanding']);
        $this->assertDatabaseHas('payment_allocations', ['amount' => '400.07']);
    }

    #[DataProvider('invalidAllocationAmounts')]
    public function test_rejects_invalid_or_excessive_allocations(string $amount): void
    {
        $user = User::factory()->create();
        $shift = $this->confirmedShift($user);
        $payment = $this->payment($user, $shift->planned_driver_id, '1500.00');
        $obligation = BoundaryObligation::query()->sole();

        $this->actingAs($user)->post(route('collections.allocate', [$user->currentTeam, $payment]), ['boundary_obligation_id' => $obligation->id, 'amount' => $amount])
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public static function invalidAllocationAmounts(): array
    {
        return ['zero' => ['0'], 'negative' => ['-1'], 'over boundary' => ['1000.01'], 'over payment' => ['1500.01'], 'fractional centavo' => ['0.001']];
    }

    public function test_cannot_allocate_a_receipt_to_another_driver(): void
    {
        $user = User::factory()->create();
        $shift = $this->confirmedShift($user);
        $otherDriver = Driver::factory()->create(['team_id' => $user->current_team_id]);
        $payment = $this->payment($user, $otherDriver->id, '100.00');
        $obligation = BoundaryObligation::query()->sole();

        $this->actingAs($user)->post(route('collections.allocate', [$user->currentTeam, $payment]), ['boundary_obligation_id' => $obligation->id, 'amount' => '100.00'])
            ->assertSessionHasErrors(['boundary_obligation_id']);

        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_reversal_reopens_boundary_without_deleting_ledger_rows(): void
    {
        $user = User::factory()->create();
        $shift = $this->confirmedShift($user);
        $obligation = BoundaryObligation::query()->sole();
        $payment = $this->payment($user, $shift->planned_driver_id, '1000.00');
        PaymentAllocation::query()->create(['team_id' => $shift->team_id, 'payment_id' => $payment->id, 'boundary_obligation_id' => $obligation->id, 'driver_id' => $shift->planned_driver_id, 'amount' => '1000.00']);

        $this->actingAs($user)->post(route('collections.reverse', [$user->currentTeam, $payment]), ['reason' => 'Incorrect receipt'])
            ->assertSessionHasNoErrors();

        $this->assertSame('1000.00', app(BoundaryBalance::class)->obligation($obligation)['outstanding']);
        $this->assertSame('0.00', app(BoundaryBalance::class)->available($payment->fresh()));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseHas('payment_reversals', ['payment_id' => $payment->id, 'reason' => 'Incorrect receipt']);
    }

    public function test_adjustment_cannot_reduce_boundary_below_paid_amount(): void
    {
        $user = User::factory()->create();
        $shift = $this->confirmedShift($user);
        $obligation = BoundaryObligation::query()->sole();
        $payment = $this->payment($user, $shift->planned_driver_id, '900.00');
        PaymentAllocation::query()->create(['team_id' => $shift->team_id, 'payment_id' => $payment->id, 'boundary_obligation_id' => $obligation->id, 'driver_id' => $shift->planned_driver_id, 'amount' => '900.00']);

        $this->actingAs($user)->post(route('collections.adjust', [$user->currentTeam, $obligation]), ['amount' => '-101.00', 'reason' => 'Waiver', 'submission_id' => 'a731a11d-8dc5-4604-8cd1-769126c10ae1'])
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseCount('boundary_adjustments', 0);
    }

    public function test_adjustment_preserves_original_obligation_amount(): void
    {
        $user = User::factory()->create();
        $this->confirmedShift($user);
        $obligation = BoundaryObligation::query()->sole();

        $this->actingAs($user)->post(route('collections.adjust', [$user->currentTeam, $obligation]), ['amount' => '-100.25', 'reason' => 'Approved shorter shift', 'submission_id' => 'a731a11d-8dc5-4604-8cd1-769126c10ae1'])
            ->assertSessionHasNoErrors();

        $this->assertSame('1000.00', $obligation->fresh()->original_amount);
        $this->assertSame('899.75', app(BoundaryBalance::class)->obligation($obligation)['outstanding']);
        $this->assertDatabaseHas('audit_entries', ['action' => 'boundary.adjusted', 'reason' => 'Approved shorter shift']);
    }

    public function test_manual_attendance_does_not_create_driving_or_boundary(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)->post(route('attendance.store', $user->currentTeam), [
            'driver_id' => $driver->id, 'in_at' => '2026-10-10T06:00', 'out_at' => '2026-10-10T14:00', 'reason' => 'Device offline',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendance_sessions', ['source' => 'manual', 'in_at' => '2026-10-09 22:00:00']);
        $this->assertDatabaseCount('driving_records', 0);
        $this->assertDatabaseCount('boundary_obligations', 0);
    }

    public function test_retried_adjustment_approval_is_idempotent(): void
    {
        $user = User::factory()->create();
        $this->confirmedShift($user);
        $obligation = BoundaryObligation::query()->sole();
        $payload = ['amount' => '-100.25', 'reason' => 'Approved shorter shift', 'submission_id' => 'a731a11d-8dc5-4604-8cd1-769126c10ae1'];
        $this->actingAs($user)->post(route('collections.adjust', [$user->currentTeam, $obligation]), $payload)->assertSessionHasNoErrors();

        $this->post(route('collections.adjust', [$user->currentTeam, $obligation]), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('boundary_adjustments', 1);
        $this->assertSame('899.75', app(BoundaryBalance::class)->obligation($obligation)['outstanding']);
        $this->assertSame(1, AuditEntry::query()->where('action', 'boundary.adjusted')->count());
    }

    public function test_corrections_preserve_previous_attendance_and_reason(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id]);
        $session = AttendanceSession::query()->create(['team_id' => $user->current_team_id, 'driver_id' => $driver->id, 'source' => 'manual', 'in_at' => '2026-10-09 22:00:00']);

        $this->actingAs($user)->patch(route('attendance.update', [$user->currentTeam, $session]), [
            'driver_id' => $driver->id, 'in_at' => '2026-10-10T06:05', 'out_at' => '2026-10-10T14:00', 'reason' => 'Corrected paper log',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendance_corrections', ['attendance_session_id' => $session->id, 'reason' => 'Corrected paper log', 'corrected_by' => $user->id]);
        $this->assertSame('2026-10-09 22:05:00', $session->fresh()->in_at->format('Y-m-d H:i:s'));
    }

    public function test_open_attendance_prevents_another_check_in(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id]);
        AttendanceSession::query()->create(['team_id' => $user->current_team_id, 'driver_id' => $driver->id, 'source' => 'manual', 'in_at' => '2026-10-09 22:00:00']);

        $this->actingAs($user)->post(route('attendance.store', $user->currentTeam), [
            'driver_id' => $driver->id, 'in_at' => '2026-10-10T10:00', 'out_at' => null, 'reason' => 'Manual entry',
        ])->assertSessionHasErrors(['in_at']);

        $this->assertDatabaseCount('attendance_sessions', 1);
    }

    public function test_cross_team_route_binding_returns_404_before_mutation(): void
    {
        $user = User::factory()->create();
        $foreignShift = Assignment::factory()->create();

        $this->actingAs($user)->post(route('assignments.cancel', [$user->currentTeam, $foreignShift]), ['reason' => 'Attempted change'])
            ->assertNotFound();

        $this->assertSame(AssignmentStatus::Planned, $foreignShift->fresh()->status);
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_new_operations_are_restricted_to_existing_owners(TeamRole $role): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $owner->currentTeam->members()->attach($user, ['role' => $role->value]);

        $this->actingAs($user)->post(route('drivers.store', $owner->currentTeam), ['code' => 'D01', 'name' => 'Driver', 'is_active' => true])
            ->assertForbidden();

        $this->assertDatabaseCount('drivers', 0);
    }

    public static function unprivilegedRoles(): array
    {
        return ['member' => [TeamRole::Member], 'admin pending approval' => [TeamRole::Admin]];
    }

    private function shift(User $user, array $attributes = []): Assignment
    {
        return Assignment::factory()->create([
            'team_id' => $user->current_team_id, 'starts_at' => '2026-10-09 22:00:00', 'ends_at' => '2026-10-10 06:00:00', 'operational_date' => '2026-10-10', ...$attributes,
        ]);
    }

    public function test_confirmed_shift_cannot_be_rewritten(): void
    {
        $user = User::factory()->create();
        $shift = $this->confirmedShift($user);

        $this->actingAs($user)->patch(route('assignments.update', [$user->currentTeam, $shift]), [
            'planned_driver_id' => $shift->planned_driver_id, 'jeepney_id' => $shift->jeepney_id, 'route_id' => $shift->route_id,
            'starts_at' => '2026-10-10T07:00', 'ends_at' => '2026-10-10T14:00',
        ])->assertSessionHasErrors(['status']);

        $this->assertSame('2026-10-09 22:00:00', $shift->fresh()->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('1000.00', BoundaryObligation::query()->sole()->original_amount);
    }

    public function test_confirmed_substitute_cannot_drive_two_overlapping_shifts(): void
    {
        $user = User::factory()->create();
        $first = $this->confirmedShift($user);
        $second = $this->shift($user);

        $this->actingAs($user)->post(route('assignments.confirm', [$user->currentTeam, $second]), ['actual_driver_id' => $first->planned_driver_id, 'expected_boundary_amount' => $second->route->default_boundary_amount])
            ->assertSessionHasErrors(['actual_driver_id']);

        $this->assertDatabaseCount('driving_records', 1);
        $this->assertSame(AssignmentStatus::Planned, $second->fresh()->status);
    }

    public function test_duplicate_receipt_number_does_not_record_another_payment(): void
    {
        $this->travelTo('2026-10-10 12:00:00');
        $user = User::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id]);
        $payment = $this->payment($user, $driver->id, '100.00');

        $this->actingAs($user)->post(route('collections.store', $user->currentTeam), [
            'driver_id' => $driver->id, 'receipt_number' => $payment->receipt_number, 'amount' => '100.00', 'collected_at' => '2026-10-10T09:00',
        ])->assertSessionHasErrors(['receipt_number']);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_confirmation_rejects_a_boundary_that_changed_after_review(): void
    {
        $user = User::factory()->create();
        $shift = $this->shift($user);
        $shift->route->update(['default_boundary_amount' => '1500.00']);

        $this->actingAs($user)->post(route('assignments.confirm', [$user->currentTeam, $shift]), [
            'actual_driver_id' => $shift->planned_driver_id, 'expected_boundary_amount' => '1000.00',
        ])->assertSessionHasErrors(['expected_boundary_amount']);

        $this->assertDatabaseCount('driving_records', 0);
        $this->assertSame(AssignmentStatus::Planned, $shift->fresh()->status);
    }

    public function test_duplicate_allocation_is_rejected_without_changing_balances(): void
    {
        $user = User::factory()->create();
        $shift = $this->confirmedShift($user);
        $payment = $this->payment($user, $shift->planned_driver_id, '500.00');
        $obligation = BoundaryObligation::query()->sole();
        PaymentAllocation::query()->create(['team_id' => $shift->team_id, 'payment_id' => $payment->id, 'boundary_obligation_id' => $obligation->id, 'driver_id' => $shift->planned_driver_id, 'amount' => '100.00']);

        $this->actingAs($user)->post(route('collections.allocate', [$user->currentTeam, $payment]), ['boundary_obligation_id' => $obligation->id, 'amount' => '100.00'])
            ->assertSessionHasErrors(['boundary_obligation_id']);

        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertSame('900.00', app(BoundaryBalance::class)->obligation($obligation)['outstanding']);
    }

    public function test_reversed_receipt_cannot_be_allocated(): void
    {
        $user = User::factory()->create();
        $shift = $this->confirmedShift($user);
        $payment = $this->payment($user, $shift->planned_driver_id, '500.00');
        app(ManageCollections::class)->reverse($user->currentTeam, $user, $payment, 'Incorrect receipt');
        $obligation = BoundaryObligation::query()->sole();

        $this->actingAs($user)->post(route('collections.allocate', [$user->currentTeam, $payment]), ['boundary_obligation_id' => $obligation->id, 'amount' => '100.00'])
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_duplicate_reversal_preserves_a_single_reversal_entry(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $user->current_team_id]);
        $payment = $this->payment($user, $driver->id, '500.00');
        app(ManageCollections::class)->reverse($user->currentTeam, $user, $payment, 'Incorrect receipt');

        $this->actingAs($user)->post(route('collections.reverse', [$user->currentTeam, $payment]), ['reason' => 'Repeated request'])
            ->assertSessionHasErrors(['reason']);

        $this->assertDatabaseCount('payment_reversals', 1);
    }

    public function test_a_foreign_payment_is_hidden_from_allocations(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $other->current_team_id]);
        $payment = $this->payment($other, $driver->id, '500.00');

        $this->actingAs($user)->post(route('collections.reverse', [$user->currentTeam, $payment]), ['reason' => 'Attempted reversal'])
            ->assertNotFound();

        $this->assertDatabaseCount('payment_reversals', 0);
    }

    private function confirmedShift(User $user): Assignment
    {
        $shift = $this->shift($user);
        $shift->route->update(['default_boundary_amount' => '1000.00']);
        app(ManageAssignments::class)->confirm($user->currentTeam, $user, $shift, $shift->planned_driver_id);

        return $shift->fresh();
    }

    private function payment(User $user, int $driverId, string $amount): Payment
    {
        return Payment::query()->create([
            'team_id' => $user->current_team_id, 'driver_id' => $driverId, 'amount' => $amount,
            'collected_at' => '2026-10-10 01:00:00', 'received_by' => $user->id, 'receipt_number' => 'R-'.fake()->unique()->numerify('#####'),
        ]);
    }
}
