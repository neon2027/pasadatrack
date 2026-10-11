<?php

namespace Tests\Feature\Foundation;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Scheduling\AssignmentStatus;
use App\Models\Attendance\AttendanceCorrection;
use App\Models\Attendance\AttendanceSession;
use App\Models\Attendance\BiometricDevice;
use App\Models\Attendance\BiometricDeviceUser;
use App\Models\Attendance\BiometricEvent;
use App\Models\Audit\AuditEntry;
use App\Models\Boundary\BoundaryAdjustment;
use App\Models\Boundary\BoundaryObligation;
use App\Models\Boundary\Payment;
use App\Models\Boundary\PaymentAllocation;
use App\Models\Boundary\PaymentReversal;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Jeepney;
use App\Models\Fleet\Route;
use App\Models\Scheduling\Assignment;
use App\Models\Scheduling\DrivingRecord;
use App\Models\Scheduling\VehicleUnavailability;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PasadaTrackSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_foundation_migrations_can_roll_back_and_recreate_every_domain_table(): void
    {
        $migrationFiles = glob(database_path('migrations/2026_10_09_0000??_create_*_table.php')) ?: [];
        sort($migrationFiles, SORT_STRING);
        self::assertCount(17, $migrationFiles);

        $migrations = array_map(static fn (string $path): Migration => require $path, $migrationFiles);
        $team = Team::factory()->create();
        $tables = [
            'drivers', 'jeepneys', 'routes', 'assignments', 'vehicle_unavailabilities',
            'driving_records', 'boundary_obligations', 'boundary_adjustments', 'payments',
            'payment_allocations', 'payment_reversals', 'biometric_devices', 'biometric_device_users',
            'biometric_events', 'attendance_sessions', 'attendance_corrections', 'audit_entries',
        ];

        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
        foreach ($tables as $table) {
            self::assertFalse(Schema::hasTable($table));
        }
        self::assertTrue(Schema::hasTable('teams'));
        $this->assertModelExists($team);

        foreach ($migrations as $migration) {
            $migration->up();
        }
        foreach ($tables as $table) {
            self::assertTrue(Schema::hasTable($table));
        }
        self::assertTrue(Schema::hasTable('teams'));
        $this->assertModelExists($team);
        $this->assertModelExists(Assignment::factory()->create());
    }

    public function test_master_factories_and_scheduling_factory_share_an_explicit_tenant(): void
    {
        $team = Team::factory()->create();
        $assignment = Assignment::factory()->for($team)->create();

        self::assertSame($team->id, $assignment->plannedDriver->team_id);
        self::assertSame($team->id, $assignment->jeepney->team_id);
        self::assertSame($team->id, $assignment->route->team_id);
        self::assertSame(AssignmentStatus::Planned, $assignment->status);
        self::assertSame('1200.00', $assignment->route->default_boundary_amount);
    }

    public function test_for_team_scopes_qualify_the_tenant_column_even_with_a_tenant_join(): void
    {
        $driver = Driver::factory()->create();
        Driver::factory()->create();

        $ids = Driver::forTeam($driver->team)->join('teams', 'teams.id', '=', 'drivers.team_id')->pluck('drivers.id')->all();

        self::assertSame([$driver->id], $ids);
        self::assertSame(1, Driver::forTeam($driver->team_id)->count());
    }

    #[DataProvider('masterIdentifiersAreUniqueWithinATeamButReusableByAnotherTeamData')]
    public function test_master_identifiers_are_unique_within_a_team_but_reusable_by_another_team(string $model, string $column, string $value): void
    {
        $team = Team::factory()->create();
        $model::factory()->for($team)->create([$column => $value]);
        $other = $model::factory()->create([$column => $value]);

        self::assertNotSame($team->id, $other->team_id);
        $this->assertThrows(fn () => $model::factory()->for($team)->create([$column => $value]), QueryException::class);
        $this->assertDatabaseCount((new $model)->getTable(), 2);
    }

    #[DataProvider('assignmentsRejectParentsFromAnotherTenantData')]
    public function test_assignments_reject_parents_from_another_tenant(string $model, string $column): void
    {
        $team = Team::factory()->create();
        $foreignParent = $model::factory()->create();

        $this->assertThrows(fn () => Assignment::factory()->for($team)->create([$column => $foreignParent->id]), QueryException::class);
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_vehicle_unavailability_references_a_jeepney_in_its_own_tenant(): void
    {
        $jeepney = Jeepney::factory()->create();
        $unavailability = VehicleUnavailability::query()->create([
            'team_id' => $jeepney->team_id,
            'jeepney_id' => $jeepney->id,
            'starts_at' => '2026-10-09 00:00:00',
            'ends_at' => '2026-10-09 06:00:00',
            'reason' => 'Scheduled brake inspection',
        ]);

        self::assertSame($jeepney->id, $unavailability->jeepney()->firstOrFail()->id);
        $this->assertThrows(fn () => DB::table('vehicle_unavailabilities')->where('id', $unavailability->id)->update([
            'team_id' => Team::factory()->create()->id,
        ]), QueryException::class);
    }

    public function test_utc_instants_and_local_business_dates_remain_separate_across_midnight(): void
    {
        $assignment = Assignment::factory()->create([
            'starts_at' => '2026-10-09 21:00:00',
            'ends_at' => '2026-10-10 05:00:00',
            'operational_date' => '2026-10-10',
        ])->fresh();

        self::assertInstanceOf(CarbonImmutable::class, $assignment->starts_at);
        self::assertSame('2026-10-09T21:00:00+00:00', $assignment->starts_at->toIso8601String());
        self::assertSame('2026-10-10 05:00:00', $assignment->ends_at->toDateTimeString());
        self::assertSame('2026-10-10', $assignment->operational_date->toDateString());
    }

    #[DataProvider('assignmentTransitionEligibilityIsExplicitAndTerminalStatesStayTerminalData')]
    public function test_assignment_transition_eligibility_is_explicit_and_terminal_states_stay_terminal(AssignmentStatus $from, AssignmentStatus $to, bool $allowed): void
    {
        self::assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_planning_and_cancelling_assignments_never_create_driving_records_or_obligations(): void
    {
        $planned = Assignment::factory()->create();
        $planned->update(['status' => AssignmentStatus::Cancelled]);
        $cancelled = Assignment::factory()->cancelled()->create();

        self::assertSame(AssignmentStatus::Cancelled, $planned->fresh()->status);
        self::assertFalse($cancelled->drivingRecord()->exists());
        $this->assertDatabaseCount('driving_records', 0);
        $this->assertDatabaseCount('boundary_obligations', 0);
    }

    public function test_a_confirmed_record_can_capture_a_substitute_driver_and_a_historical_price_snapshot(): void
    {
        $assignment = Assignment::factory()->create(['status' => AssignmentStatus::Confirmed]);
        $substitute = Driver::factory()->create(['team_id' => $assignment->team_id]);
        $record = $this->foundationRecord($assignment, $substitute);
        $obligation = $this->foundationObligation($record);

        $assignment->route->update(['default_boundary_amount' => '1500.75']);

        self::assertSame('1200.00', $record->fresh()->boundary_amount);
        self::assertSame('1200.00', $obligation->fresh()->original_amount);
        self::assertSame($substitute->id, $record->actualDriver->id);
        self::assertNotSame($substitute->id, $record->assignment->planned_driver_id);
        self::assertSame($obligation->id, $record->boundaryObligation->id);
    }

    public function test_an_assignment_has_at_most_one_confirmed_driving_record(): void
    {
        $record = $this->foundationRecord();

        $this->assertThrows(fn () => DrivingRecord::query()->create($record->getAttributes()), QueryException::class);
        $this->assertDatabaseCount('driving_records', 1);
    }

    #[DataProvider('drivingRecordsRequireConfirmationEvidenceData')]
    public function test_driving_records_require_confirmation_evidence(string $column): void
    {
        $record = $this->foundationRecord();

        $this->assertThrows(fn () => DB::table('driving_records')->where('id', $record->id)->update([$column => null]), QueryException::class);
    }

    #[DataProvider('drivingRecordsRejectACrossTenantAssignmentOrActualDriverData')]
    public function test_driving_records_reject_a_cross_tenant_assignment_or_actual_driver(string $column, string $model): void
    {
        $record = $this->foundationRecord();
        $foreign = $model::factory()->create();

        $this->assertThrows(fn () => DB::table('driving_records')->where('id', $record->id)->update([$column => $foreign->id]), QueryException::class);
    }

    public function test_an_obligation_is_unique_per_driving_record_and_must_match_its_actual_driver(): void
    {
        $obligation = $this->foundationObligation();
        $differentDriver = Driver::factory()->create(['team_id' => $obligation->team_id]);

        $this->assertThrows(fn () => BoundaryObligation::query()->create([
            'team_id' => $obligation->team_id,
            'driving_record_id' => $obligation->driving_record_id,
            'driver_id' => $obligation->driver_id,
            'original_amount' => '1200.00',
        ]), QueryException::class);
        $this->assertThrows(fn () => DB::table('boundary_obligations')->where('id', $obligation->id)->update([
            'driver_id' => $differentDriver->id,
        ]), QueryException::class);
        $this->assertDatabaseCount('boundary_obligations', 1);
    }

    #[DataProvider('approvedAdjustmentsRetainSignedDecimalAmountsWithoutRewritingTheOriginalAmountData')]
    public function test_approved_adjustments_retain_signed_decimal_amounts_without_rewriting_the_original_amount(string $amount): void
    {
        $obligation = $this->foundationObligation();
        $adjustment = BoundaryAdjustment::query()->create([
            'team_id' => $obligation->team_id,
            'boundary_obligation_id' => $obligation->id,
            'amount' => $amount,
            'reason' => 'Approved route allowance correction',
            'approved_by' => User::factory()->create()->id,
            'approved_at' => '2026-10-09 14:00:00',
        ]);

        self::assertSame($amount, $adjustment->fresh()->amount);
        self::assertSame('1200.00', $obligation->fresh()->original_amount);
    }

    #[DataProvider('adjustmentsRequireApprovalEvidenceAndAReasonData')]
    public function test_adjustments_require_approval_evidence_and_a_reason(string $column): void
    {
        $adjustment = $this->foundationAdjustment($this->foundationObligation());

        $this->assertThrows(fn () => DB::table('boundary_adjustments')->where('id', $adjustment->id)->update([$column => null]), QueryException::class);
    }

    #[DataProvider('approvedAdjustmentsCannotBeUpdatedOrDeletedThroughModelInstancesData')]
    public function test_approved_adjustments_cannot_be_updated_or_deleted_through_model_instances(string $operation): void
    {
        $adjustment = $this->foundationAdjustment($this->foundationObligation());

        $this->assertThrows(fn () => $operation === 'update'
            ? $adjustment->update(['amount' => '0.00'])
            : $adjustment->delete(), LogicException::class);
        self::assertSame('-125.50', $adjustment->fresh()->amount);
        $this->assertModelExists($adjustment);
    }

    public function test_payments_require_a_valid_driver_and_actor_and_a_team_unique_receipt(): void
    {
        $driver = Driver::factory()->create();
        $payment = $this->foundationPayment($driver);
        $this->foundationPayment(Driver::factory()->create(), 'PT-0001');

        self::assertSame('700.25', $payment->fresh()->amount);
        $this->assertThrows(fn () => $this->foundationPayment($driver, 'PT-0001'), QueryException::class);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_allocations_link_a_payment_and_obligation_for_the_same_driver_and_are_unique_per_pair(): void
    {
        $obligation = $this->foundationObligation();
        $payment = $this->foundationPayment($obligation->driver);
        $attributes = [
            'team_id' => $obligation->team_id,
            'payment_id' => $payment->id,
            'boundary_obligation_id' => $obligation->id,
            'driver_id' => $obligation->driver_id,
            'amount' => '700.25',
        ];
        $allocation = PaymentAllocation::query()->create($attributes);

        self::assertSame('700.25', $allocation->fresh()->amount);
        self::assertSame($payment->id, $allocation->payment()->firstOrFail()->id);
        self::assertSame($obligation->id, $allocation->boundaryObligation()->firstOrFail()->id);
        $this->assertThrows(fn () => PaymentAllocation::query()->create($attributes), QueryException::class);
        $this->assertDatabaseCount('payment_allocations', 1);
    }

    public function test_allocations_reject_a_different_driver_even_within_the_same_tenant(): void
    {
        $obligation = $this->foundationObligation();
        $otherDriver = Driver::factory()->create(['team_id' => $obligation->team_id]);
        $payment = $this->foundationPayment($otherDriver);

        foreach ([$obligation->driver_id, $otherDriver->id] as $driverId) {
            $this->assertThrows(fn () => PaymentAllocation::query()->create([
                'team_id' => $obligation->team_id,
                'payment_id' => $payment->id,
                'boundary_obligation_id' => $obligation->id,
                'driver_id' => $driverId,
                'amount' => '100.00',
            ]), QueryException::class);
        }
        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_allocations_reject_payment_and_obligation_parents_from_different_tenants(): void
    {
        $obligation = $this->foundationObligation();
        $payment = $this->foundationPayment(Driver::factory()->create());

        $this->assertThrows(fn () => PaymentAllocation::query()->create([
            'team_id' => $obligation->team_id,
            'payment_id' => $payment->id,
            'boundary_obligation_id' => $obligation->id,
            'driver_id' => $obligation->driver_id,
            'amount' => '100.00',
        ]), QueryException::class);
        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_a_payment_has_at_most_one_reversal_and_retains_its_original_receipt_and_amount(): void
    {
        $payment = $this->foundationPayment(Driver::factory()->create());
        $attributes = [
            'team_id' => $payment->team_id,
            'payment_id' => $payment->id,
            'reason' => 'Receipt entered in error',
            'reversed_by' => User::factory()->create()->id,
            'reversed_at' => '2026-10-09 15:00:00',
        ];
        $reversal = PaymentReversal::query()->create($attributes);

        self::assertSame($reversal->id, $payment->fresh()->reversal->id);
        self::assertSame('700.25', $payment->amount);
        self::assertSame('PT-0001', $payment->receipt_number);
        $this->assertThrows(fn () => PaymentReversal::query()->create($attributes), QueryException::class);
    }

    #[DataProvider('financialAndTenantParentsCannotBeHardDeletedWhileReferencedData')]
    public function test_financial_and_tenant_parents_cannot_be_hard_deleted_while_referenced(string $target): void
    {
        $obligation = $this->foundationObligation();
        $record = $obligation->drivingRecord;
        $payment = $this->foundationPayment($obligation->driver);
        $parents = [
            'team' => ['teams', $obligation->team_id],
            'driver' => ['drivers', $obligation->driver_id],
            'assignment' => ['assignments', $record->assignment_id],
            'jeepney' => ['jeepneys', $record->assignment->jeepney_id],
            'route' => ['routes', $record->assignment->route_id],
            'record' => ['driving_records', $record->id],
            'confirmation actor' => ['users', $record->confirmed_by],
            'receipt actor' => ['users', $payment->received_by],
        ];
        [$table, $id] = $parents[$target];

        $this->assertThrows(fn () => DB::table($table)->where('id', $id)->delete(), QueryException::class);
        $this->assertModelExists($obligation);
        $this->assertModelExists($payment);
    }

    #[DataProvider('allocatedObligationsAndPaymentsCannotCascadeAwayFinancialHistoryData')]
    public function test_allocated_obligations_and_payments_cannot_cascade_away_financial_history(string $table): void
    {
        $obligation = $this->foundationObligation();
        $payment = $this->foundationPayment($obligation->driver);
        PaymentAllocation::query()->create([
            'team_id' => $obligation->team_id,
            'payment_id' => $payment->id,
            'boundary_obligation_id' => $obligation->id,
            'driver_id' => $obligation->driver_id,
            'amount' => '700.25',
        ]);
        $id = $table === 'payments' ? $payment->id : $obligation->id;

        $this->assertThrows(fn () => DB::table($table)->where('id', $id)->delete(), QueryException::class);
        $this->assertDatabaseCount('payment_allocations', 1);
    }

    public function test_an_unknown_biometric_user_scan_persists_without_creating_a_mapping_or_attendance(): void
    {
        $device = BiometricDevice::factory()->create();

        $event = $this->foundationEvent($device)->fresh();

        self::assertSame('unknown-007', $event->external_user_id);
        self::assertSame(['user' => 'unknown-007', 'direction' => 'in', 'vendor_code' => 7], $event->raw_metadata);
        self::assertSame('2026-10-09T21:00:00+00:00', $event->occurred_at->toIso8601String());
        $this->assertModelExists($event);
        $this->assertDatabaseCount('biometric_device_users', 0);
        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_biometric_event_keys_deduplicate_per_device_rather_than_globally(): void
    {
        $device = BiometricDevice::factory()->create();
        $this->foundationEvent($device);
        $anotherDevice = BiometricDevice::factory()->create(['team_id' => $device->team_id]);
        $this->foundationEvent($anotherDevice);

        $this->assertThrows(fn () => $this->foundationEvent($device, 'scan-1', 'different-user'), QueryException::class);
        $this->assertDatabaseCount('biometric_events', 2);
    }

    public function test_external_users_map_uniquely_per_device_and_only_to_same_tenant_drivers(): void
    {
        $device = BiometricDevice::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $device->team_id]);
        $attributes = [
            'team_id' => $device->team_id,
            'biometric_device_id' => $device->id,
            'external_user_id' => '007',
            'driver_id' => $driver->id,
        ];
        $mapping = BiometricDeviceUser::query()->create($attributes);

        self::assertSame($driver->id, $mapping->driver()->firstOrFail()->id);
        $this->assertThrows(fn () => BiometricDeviceUser::query()->create($attributes), QueryException::class);
        $this->assertThrows(fn () => BiometricDeviceUser::query()->create(array_replace($attributes, [
            'external_user_id' => '008',
            'driver_id' => Driver::factory()->create()->id,
        ])), QueryException::class);
        $this->assertDatabaseCount('biometric_device_users', 1);
    }

    public function test_attendance_supports_open_manual_sessions_without_biometric_events(): void
    {
        $driver = Driver::factory()->create();

        $session = AttendanceSession::query()->create([
            'team_id' => $driver->team_id,
            'driver_id' => $driver->id,
            'source' => AttendanceSource::Manual,
            'in_at' => '2026-10-09 21:00:00',
        ])->fresh();

        self::assertSame(AttendanceSource::Manual, $session->source);
        self::assertNull($session->out_at);
        self::assertNull($session->in_event_id);
        self::assertNull($session->out_event_id);
    }

    public function test_biometric_attendance_links_same_tenant_evidence_and_keeps_correction_history(): void
    {
        $device = BiometricDevice::factory()->create();
        $driver = Driver::factory()->create(['team_id' => $device->team_id]);
        $in = $this->foundationEvent($device, 'in-1', '007');
        $out = $this->foundationEvent($device, 'out-1', '007');
        $session = AttendanceSession::query()->create([
            'team_id' => $driver->team_id,
            'driver_id' => $driver->id,
            'source' => AttendanceSource::Biometric,
            'in_event_id' => $in->id,
            'out_event_id' => $out->id,
            'in_at' => '2026-10-09 21:00:00',
            'out_at' => '2026-10-10 05:00:00',
        ]);
        $correction = AttendanceCorrection::query()->create([
            'team_id' => $session->team_id,
            'attendance_session_id' => $session->id,
            'reason' => 'Supervisor corrected a late clock-out',
            'corrected_by' => User::factory()->create()->id,
            'before' => ['out_at' => '2026-10-10 05:00:00'],
            'after' => ['out_at' => '2026-10-10 04:30:00'],
            'corrected_at' => '2026-10-10 06:00:00',
        ]);

        self::assertSame($in->id, $session->inEvent()->firstOrFail()->id);
        self::assertSame($out->id, $session->outEvent()->firstOrFail()->id);
        self::assertSame(['out_at' => '2026-10-10 05:00:00'], $correction->fresh()->before);
        self::assertSame(['out_at' => '2026-10-10 04:30:00'], $correction->after);
        $this->assertDatabaseHas('attendance_sessions', ['id' => $session->id, 'out_at' => '2026-10-10 05:00:00']);
    }

    #[DataProvider('attendanceRejectsBiometricEvidenceFromAnotherTenantData')]
    public function test_attendance_rejects_biometric_evidence_from_another_tenant(string $column): void
    {
        $driver = Driver::factory()->create();
        $foreignEvent = $this->foundationEvent(BiometricDevice::factory()->create());

        $this->assertThrows(fn () => AttendanceSession::query()->create([
            'team_id' => $driver->team_id,
            'driver_id' => $driver->id,
            'source' => AttendanceSource::Biometric,
            $column => $foreignEvent->id,
            'in_at' => '2026-10-09 21:00:00',
        ]), QueryException::class);
        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_audit_entries_retain_before_after_and_subject_identifiers_independently_of_subjects(): void
    {
        $driver = Driver::factory()->create();
        $entry = AuditEntry::query()->create([
            'team_id' => $driver->team_id,
            'actor_id' => User::factory()->create()->id,
            'action' => 'driver.updated',
            'subject_type' => 'driver',
            'subject_id' => $driver->id,
            'before' => ['is_active' => true],
            'after' => ['is_active' => false],
            'reason' => 'Driver retired',
            'occurred_at' => '2026-10-09 16:00:00',
        ]);

        $driver->delete();

        self::assertSame($driver->id, $entry->fresh()->subject_id);
        self::assertSame(['is_active' => true], $entry->before);
        self::assertSame(['is_active' => false], $entry->after);
        $this->assertModelExists($entry);
        $this->assertThrows(fn () => $entry->update(['reason' => 'Changed history']), LogicException::class);
    }

    public function test_the_optional_demo_seeder_is_repeatable_and_leaves_existing_master_edits_and_ledgers_alone(): void
    {
        $this->seed(PasadaTrackSeeder::class);
        $route = Route::query()->where('code', 'CUB-QPO')->firstOrFail();
        $route->update(['default_boundary_amount' => '1350.00']);

        $this->seed(PasadaTrackSeeder::class);

        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseCount('drivers', 3);
        $this->assertDatabaseCount('jeepneys', 2);
        $this->assertDatabaseCount('routes', 2);
        $this->assertDatabaseCount('biometric_devices', 1);
        self::assertSame('1350.00', $route->fresh()->default_boundary_amount);
        foreach (['assignments', 'driving_records', 'boundary_obligations', 'payments', 'biometric_events', 'users', 'team_members'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_the_demo_seeder_refuses_production_without_inserting_any_rows(): void
    {
        $environment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->assertThrows(fn () => (new PasadaTrackSeeder)->run(), LogicException::class);
            $this->assertDatabaseCount('teams', 0);
        } finally {
            app()->detectEnvironment(fn (): string => $environment);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function masterIdentifiersAreUniqueWithinATeamButReusableByAnotherTeamData(): array
    {
        return [
            'driver code' => [Driver::class, 'code', 'DRV-001'],
            'jeepney plate' => [Jeepney::class, 'plate_number', 'ABC-1234'],
            'route code' => [Route::class, 'code', 'CUB-QPO'],
            'device code' => [BiometricDevice::class, 'code', 'TERMINAL-01'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function assignmentsRejectParentsFromAnotherTenantData(): array
    {
        return [
            'driver' => [Driver::class, 'planned_driver_id'],
            'jeepney' => [Jeepney::class, 'jeepney_id'],
            'route' => [Route::class, 'route_id'],
        ];
    }

    /**
     * @return array<string, array{0: AssignmentStatus, 1: AssignmentStatus, 2: bool}>
     */
    public static function assignmentTransitionEligibilityIsExplicitAndTerminalStatesStayTerminalData(): array
    {
        return [
            'planned to planned' => [AssignmentStatus::Planned, AssignmentStatus::Planned, false],
            'planned to cancelled' => [AssignmentStatus::Planned, AssignmentStatus::Cancelled, true],
            'planned to confirmed' => [AssignmentStatus::Planned, AssignmentStatus::Confirmed, true],
            'cancelled to planned' => [AssignmentStatus::Cancelled, AssignmentStatus::Planned, false],
            'cancelled to confirmed' => [AssignmentStatus::Cancelled, AssignmentStatus::Confirmed, false],
            'cancelled to cancelled' => [AssignmentStatus::Cancelled, AssignmentStatus::Cancelled, false],
            'confirmed to planned' => [AssignmentStatus::Confirmed, AssignmentStatus::Planned, false],
            'confirmed to cancelled' => [AssignmentStatus::Confirmed, AssignmentStatus::Cancelled, false],
            'confirmed to confirmed' => [AssignmentStatus::Confirmed, AssignmentStatus::Confirmed, false],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function drivingRecordsRequireConfirmationEvidenceData(): array
    {
        return [
            'confirmed_by' => ['confirmed_by'],
            'confirmed_at' => ['confirmed_at'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function drivingRecordsRejectACrossTenantAssignmentOrActualDriverData(): array
    {
        return [
            'assignment' => ['assignment_id', Assignment::class],
            'actual driver' => ['actual_driver_id', Driver::class],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function approvedAdjustmentsRetainSignedDecimalAmountsWithoutRewritingTheOriginalAmountData(): array
    {
        return ['reduction' => ['-125.50'], 'increase' => ['25.75']];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function adjustmentsRequireApprovalEvidenceAndAReasonData(): array
    {
        return [
            'approved_by' => ['approved_by'],
            'approved_at' => ['approved_at'],
            'reason' => ['reason'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function approvedAdjustmentsCannotBeUpdatedOrDeletedThroughModelInstancesData(): array
    {
        return [
            'update' => ['update'],
            'delete' => ['delete'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function financialAndTenantParentsCannotBeHardDeletedWhileReferencedData(): array
    {
        return [
            'team' => ['team'],
            'driver' => ['driver'],
            'assignment' => ['assignment'],
            'jeepney' => ['jeepney'],
            'route' => ['route'],
            'record' => ['record'],
            'confirmation actor' => ['confirmation actor'],
            'receipt actor' => ['receipt actor'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allocatedObligationsAndPaymentsCannotCascadeAwayFinancialHistoryData(): array
    {
        return [
            'payments' => ['payments'],
            'boundary_obligations' => ['boundary_obligations'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function attendanceRejectsBiometricEvidenceFromAnotherTenantData(): array
    {
        return [
            'in_event_id' => ['in_event_id'],
            'out_event_id' => ['out_event_id'],
        ];
    }

    /** Schema fixtures insert facts explicitly; they do not implement financial workflows. */
    private function foundationRecord(?Assignment $assignment = null, ?Driver $actualDriver = null): DrivingRecord
    {
        $assignment ??= Assignment::factory()->create(['status' => AssignmentStatus::Confirmed]);
        $actualDriver ??= $assignment->plannedDriver;

        return DrivingRecord::query()->create([
            'team_id' => $assignment->team_id,
            'assignment_id' => $assignment->id,
            'actual_driver_id' => $actualDriver->id,
            'confirmed_by' => User::factory()->create()->id,
            'confirmed_at' => '2026-10-09 12:00:00',
            'boundary_amount' => '1200.00',
            'driving_date' => '2026-10-09',
        ]);
    }

    private function foundationObligation(?DrivingRecord $record = null): BoundaryObligation
    {
        $record ??= $this->foundationRecord();

        return BoundaryObligation::query()->create([
            'team_id' => $record->team_id,
            'driving_record_id' => $record->id,
            'driver_id' => $record->actual_driver_id,
            'original_amount' => $record->boundary_amount,
        ]);
    }

    private function foundationPayment(Driver $driver, string $receipt = 'PT-0001'): Payment
    {
        return Payment::query()->create([
            'team_id' => $driver->team_id,
            'driver_id' => $driver->id,
            'amount' => '700.25',
            'collected_at' => '2026-10-09 13:00:00',
            'received_by' => User::factory()->create()->id,
            'receipt_number' => $receipt,
        ]);
    }

    private function foundationEvent(BiometricDevice $device, string $key = 'scan-1', string $externalUser = 'unknown-007'): BiometricEvent
    {
        return BiometricEvent::query()->create([
            'team_id' => $device->team_id,
            'biometric_device_id' => $device->id,
            'event_key' => $key,
            'external_user_id' => $externalUser,
            'raw_metadata' => ['user' => $externalUser, 'direction' => 'in', 'vendor_code' => 7],
            'occurred_at' => '2026-10-09 21:00:00',
        ]);
    }

    private function foundationAdjustment(BoundaryObligation $obligation): BoundaryAdjustment
    {
        return BoundaryAdjustment::query()->create([
            'team_id' => $obligation->team_id,
            'boundary_obligation_id' => $obligation->id,
            'amount' => '-125.50',
            'reason' => 'Approved shortened trip allowance',
            'approved_by' => User::factory()->create()->id,
            'approved_at' => '2026-10-09 14:00:00',
        ]);
    }
}
