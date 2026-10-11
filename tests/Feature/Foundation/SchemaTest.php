<?php

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
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Schema fixtures insert facts explicitly; they do not implement financial workflows. */
function foundationRecord(?Assignment $assignment = null, ?Driver $actualDriver = null): DrivingRecord
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

function foundationObligation(?DrivingRecord $record = null): BoundaryObligation
{
    $record ??= foundationRecord();

    return BoundaryObligation::query()->create([
        'team_id' => $record->team_id,
        'driving_record_id' => $record->id,
        'driver_id' => $record->actual_driver_id,
        'original_amount' => $record->boundary_amount,
    ]);
}

function foundationPayment(Driver $driver, string $receipt = 'PT-0001'): Payment
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

function foundationEvent(BiometricDevice $device, string $key = 'scan-1', string $externalUser = 'unknown-007'): BiometricEvent
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

function foundationAdjustment(BoundaryObligation $obligation): BoundaryAdjustment
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

test('the foundation migrations can roll back and recreate every domain table', function () {
    $migrationFiles = glob(database_path('migrations/2026_10_09_0000??_create_*_table.php')) ?: [];
    sort($migrationFiles, SORT_STRING);
    expect($migrationFiles)->toHaveCount(17);

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
        expect(Schema::hasTable($table))->toBeFalse();
    }
    expect(Schema::hasTable('teams'))->toBeTrue();
    $this->assertModelExists($team);

    foreach ($migrations as $migration) {
        $migration->up();
    }
    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    expect(Schema::hasTable('teams'))->toBeTrue();
    $this->assertModelExists($team);
    $this->assertModelExists(Assignment::factory()->create());
});

test('master factories and scheduling factory share an explicit tenant', function () {
    $team = Team::factory()->create();
    $assignment = Assignment::factory()->for($team)->create();

    expect($assignment->plannedDriver->team_id)->toBe($team->id)
        ->and($assignment->jeepney->team_id)->toBe($team->id)
        ->and($assignment->route->team_id)->toBe($team->id)
        ->and($assignment->status)->toBe(AssignmentStatus::Planned)
        ->and($assignment->route->default_boundary_amount)->toBe('1200.00');
});

test('forTeam scopes qualify the tenant column even with a tenant join', function () {
    $driver = Driver::factory()->create();
    Driver::factory()->create();

    $ids = Driver::forTeam($driver->team)->join('teams', 'teams.id', '=', 'drivers.team_id')->pluck('drivers.id')->all();

    expect($ids)->toBe([$driver->id]);
    expect(Driver::forTeam($driver->team_id)->count())->toBe(1);
});

test('master identifiers are unique within a team but reusable by another team', function (string $model, string $column, string $value) {
    $team = Team::factory()->create();
    $model::factory()->for($team)->create([$column => $value]);
    $other = $model::factory()->create([$column => $value]);

    expect($other->team_id)->not->toBe($team->id);
    expect(fn () => $model::factory()->for($team)->create([$column => $value]))->toThrow(QueryException::class);
    $this->assertDatabaseCount((new $model)->getTable(), 2);
})->with([
    'driver code' => [Driver::class, 'code', 'DRV-001'],
    'jeepney plate' => [Jeepney::class, 'plate_number', 'ABC-1234'],
    'route code' => [Route::class, 'code', 'CUB-QPO'],
    'device code' => [BiometricDevice::class, 'code', 'TERMINAL-01'],
]);

test('assignments reject parents from another tenant', function (string $model, string $column) {
    $team = Team::factory()->create();
    $foreignParent = $model::factory()->create();

    expect(fn () => Assignment::factory()->for($team)->create([$column => $foreignParent->id]))->toThrow(QueryException::class);
    $this->assertDatabaseCount('assignments', 0);
})->with([
    'driver' => [Driver::class, 'planned_driver_id'],
    'jeepney' => [Jeepney::class, 'jeepney_id'],
    'route' => [Route::class, 'route_id'],
]);

test('vehicle unavailability references a jeepney in its own tenant', function () {
    $jeepney = Jeepney::factory()->create();
    $unavailability = VehicleUnavailability::query()->create([
        'team_id' => $jeepney->team_id,
        'jeepney_id' => $jeepney->id,
        'starts_at' => '2026-10-09 00:00:00',
        'ends_at' => '2026-10-09 06:00:00',
        'reason' => 'Scheduled brake inspection',
    ]);

    expect($unavailability->jeepney()->firstOrFail()->id)->toBe($jeepney->id);
    expect(fn () => DB::table('vehicle_unavailabilities')->where('id', $unavailability->id)->update([
        'team_id' => Team::factory()->create()->id,
    ]))->toThrow(QueryException::class);
});

test('UTC instants and local business dates remain separate across midnight', function () {
    $assignment = Assignment::factory()->create([
        'starts_at' => '2026-10-09 21:00:00',
        'ends_at' => '2026-10-10 05:00:00',
        'operational_date' => '2026-10-10',
    ])->fresh();

    expect($assignment->starts_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($assignment->starts_at->toIso8601String())->toBe('2026-10-09T21:00:00+00:00')
        ->and($assignment->ends_at->toDateTimeString())->toBe('2026-10-10 05:00:00')
        ->and($assignment->operational_date->toDateString())->toBe('2026-10-10');
});

test('assignment transition eligibility is explicit and terminal states stay terminal', function (AssignmentStatus $from, AssignmentStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'planned to planned' => [AssignmentStatus::Planned, AssignmentStatus::Planned, false],
    'planned to cancelled' => [AssignmentStatus::Planned, AssignmentStatus::Cancelled, true],
    'planned to confirmed' => [AssignmentStatus::Planned, AssignmentStatus::Confirmed, true],
    'cancelled to planned' => [AssignmentStatus::Cancelled, AssignmentStatus::Planned, false],
    'cancelled to confirmed' => [AssignmentStatus::Cancelled, AssignmentStatus::Confirmed, false],
    'cancelled to cancelled' => [AssignmentStatus::Cancelled, AssignmentStatus::Cancelled, false],
    'confirmed to planned' => [AssignmentStatus::Confirmed, AssignmentStatus::Planned, false],
    'confirmed to cancelled' => [AssignmentStatus::Confirmed, AssignmentStatus::Cancelled, false],
    'confirmed to confirmed' => [AssignmentStatus::Confirmed, AssignmentStatus::Confirmed, false],
]);

test('planning and cancelling assignments never create driving records or obligations', function () {
    $planned = Assignment::factory()->create();
    $planned->update(['status' => AssignmentStatus::Cancelled]);
    $cancelled = Assignment::factory()->cancelled()->create();

    expect($planned->fresh()->status)->toBe(AssignmentStatus::Cancelled)
        ->and($cancelled->drivingRecord()->exists())->toBeFalse();
    $this->assertDatabaseCount('driving_records', 0);
    $this->assertDatabaseCount('boundary_obligations', 0);
});

test('a confirmed record can capture a substitute driver and a historical price snapshot', function () {
    $assignment = Assignment::factory()->create(['status' => AssignmentStatus::Confirmed]);
    $substitute = Driver::factory()->create(['team_id' => $assignment->team_id]);
    $record = foundationRecord($assignment, $substitute);
    $obligation = foundationObligation($record);

    $assignment->route->update(['default_boundary_amount' => '1500.75']);

    expect($record->fresh()->boundary_amount)->toBe('1200.00')
        ->and($obligation->fresh()->original_amount)->toBe('1200.00')
        ->and($record->actualDriver->id)->toBe($substitute->id)
        ->and($record->assignment->planned_driver_id)->not->toBe($substitute->id)
        ->and($record->boundaryObligation->id)->toBe($obligation->id);
});

test('an assignment has at most one confirmed driving record', function () {
    $record = foundationRecord();

    expect(fn () => DrivingRecord::query()->create($record->getAttributes()))->toThrow(QueryException::class);
    $this->assertDatabaseCount('driving_records', 1);
});

test('driving records require confirmation evidence', function (string $column) {
    $record = foundationRecord();

    expect(fn () => DB::table('driving_records')->where('id', $record->id)->update([$column => null]))->toThrow(QueryException::class);
})->with(['confirmed_by', 'confirmed_at']);

test('driving records reject a cross tenant assignment or actual driver', function (string $column, string $model) {
    $record = foundationRecord();
    $foreign = $model::factory()->create();

    expect(fn () => DB::table('driving_records')->where('id', $record->id)->update([$column => $foreign->id]))->toThrow(QueryException::class);
})->with([
    'assignment' => ['assignment_id', Assignment::class],
    'actual driver' => ['actual_driver_id', Driver::class],
]);

test('an obligation is unique per driving record and must match its actual driver', function () {
    $obligation = foundationObligation();
    $differentDriver = Driver::factory()->create(['team_id' => $obligation->team_id]);

    expect(fn () => BoundaryObligation::query()->create([
        'team_id' => $obligation->team_id,
        'driving_record_id' => $obligation->driving_record_id,
        'driver_id' => $obligation->driver_id,
        'original_amount' => '1200.00',
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('boundary_obligations')->where('id', $obligation->id)->update([
        'driver_id' => $differentDriver->id,
    ]))->toThrow(QueryException::class);
    $this->assertDatabaseCount('boundary_obligations', 1);
});

test('approved adjustments retain signed decimal amounts without rewriting the original amount', function (string $amount) {
    $obligation = foundationObligation();
    $adjustment = BoundaryAdjustment::query()->create([
        'team_id' => $obligation->team_id,
        'boundary_obligation_id' => $obligation->id,
        'amount' => $amount,
        'reason' => 'Approved route allowance correction',
        'approved_by' => User::factory()->create()->id,
        'approved_at' => '2026-10-09 14:00:00',
    ]);

    expect($adjustment->fresh()->amount)->toBe($amount)
        ->and($obligation->fresh()->original_amount)->toBe('1200.00');
})->with(['reduction' => ['-125.50'], 'increase' => ['25.75']]);

test('adjustments require approval evidence and a reason', function (string $column) {
    $adjustment = foundationAdjustment(foundationObligation());

    expect(fn () => DB::table('boundary_adjustments')->where('id', $adjustment->id)->update([$column => null]))->toThrow(QueryException::class);
})->with(['approved_by', 'approved_at', 'reason']);

test('approved adjustments cannot be updated or deleted through model instances', function (string $operation) {
    $adjustment = foundationAdjustment(foundationObligation());

    expect(fn () => $operation === 'update'
        ? $adjustment->update(['amount' => '0.00'])
        : $adjustment->delete())->toThrow(LogicException::class);
    expect($adjustment->fresh()->amount)->toBe('-125.50');
    $this->assertModelExists($adjustment);
})->with(['update', 'delete']);

test('payments require a valid driver and actor and a team unique receipt', function () {
    $driver = Driver::factory()->create();
    $payment = foundationPayment($driver);
    foundationPayment(Driver::factory()->create(), 'PT-0001');

    expect($payment->fresh()->amount)->toBe('700.25');
    expect(fn () => foundationPayment($driver, 'PT-0001'))->toThrow(QueryException::class);
    $this->assertDatabaseCount('payments', 2);
});

test('allocations link a payment and obligation for the same driver and are unique per pair', function () {
    $obligation = foundationObligation();
    $payment = foundationPayment($obligation->driver);
    $attributes = [
        'team_id' => $obligation->team_id,
        'payment_id' => $payment->id,
        'boundary_obligation_id' => $obligation->id,
        'driver_id' => $obligation->driver_id,
        'amount' => '700.25',
    ];
    $allocation = PaymentAllocation::query()->create($attributes);

    expect($allocation->fresh()->amount)->toBe('700.25')
        ->and($allocation->payment()->firstOrFail()->id)->toBe($payment->id)
        ->and($allocation->boundaryObligation()->firstOrFail()->id)->toBe($obligation->id);
    expect(fn () => PaymentAllocation::query()->create($attributes))->toThrow(QueryException::class);
    $this->assertDatabaseCount('payment_allocations', 1);
});

test('allocations reject a different driver even within the same tenant', function () {
    $obligation = foundationObligation();
    $otherDriver = Driver::factory()->create(['team_id' => $obligation->team_id]);
    $payment = foundationPayment($otherDriver);

    foreach ([$obligation->driver_id, $otherDriver->id] as $driverId) {
        expect(fn () => PaymentAllocation::query()->create([
            'team_id' => $obligation->team_id,
            'payment_id' => $payment->id,
            'boundary_obligation_id' => $obligation->id,
            'driver_id' => $driverId,
            'amount' => '100.00',
        ]))->toThrow(QueryException::class);
    }
    $this->assertDatabaseCount('payment_allocations', 0);
});

test('allocations reject payment and obligation parents from different tenants', function () {
    $obligation = foundationObligation();
    $payment = foundationPayment(Driver::factory()->create());

    expect(fn () => PaymentAllocation::query()->create([
        'team_id' => $obligation->team_id,
        'payment_id' => $payment->id,
        'boundary_obligation_id' => $obligation->id,
        'driver_id' => $obligation->driver_id,
        'amount' => '100.00',
    ]))->toThrow(QueryException::class);
    $this->assertDatabaseCount('payment_allocations', 0);
});

test('a payment has at most one reversal and retains its original receipt and amount', function () {
    $payment = foundationPayment(Driver::factory()->create());
    $attributes = [
        'team_id' => $payment->team_id,
        'payment_id' => $payment->id,
        'reason' => 'Receipt entered in error',
        'reversed_by' => User::factory()->create()->id,
        'reversed_at' => '2026-10-09 15:00:00',
    ];
    $reversal = PaymentReversal::query()->create($attributes);

    expect($payment->fresh()->reversal->id)->toBe($reversal->id)
        ->and($payment->amount)->toBe('700.25')
        ->and($payment->receipt_number)->toBe('PT-0001');
    expect(fn () => PaymentReversal::query()->create($attributes))->toThrow(QueryException::class);
});

test('financial and tenant parents cannot be hard deleted while referenced', function (string $target) {
    $obligation = foundationObligation();
    $record = $obligation->drivingRecord;
    $payment = foundationPayment($obligation->driver);
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

    expect(fn () => DB::table($table)->where('id', $id)->delete())->toThrow(QueryException::class);
    $this->assertModelExists($obligation);
    $this->assertModelExists($payment);
})->with(['team', 'driver', 'assignment', 'jeepney', 'route', 'record', 'confirmation actor', 'receipt actor']);

test('allocated obligations and payments cannot cascade away financial history', function (string $table) {
    $obligation = foundationObligation();
    $payment = foundationPayment($obligation->driver);
    PaymentAllocation::query()->create([
        'team_id' => $obligation->team_id,
        'payment_id' => $payment->id,
        'boundary_obligation_id' => $obligation->id,
        'driver_id' => $obligation->driver_id,
        'amount' => '700.25',
    ]);
    $id = $table === 'payments' ? $payment->id : $obligation->id;

    expect(fn () => DB::table($table)->where('id', $id)->delete())->toThrow(QueryException::class);
    $this->assertDatabaseCount('payment_allocations', 1);
})->with(['payments', 'boundary_obligations']);

test('an unknown biometric user scan persists without creating a mapping or attendance', function () {
    $device = BiometricDevice::factory()->create();

    $event = foundationEvent($device)->fresh();

    expect($event->external_user_id)->toBe('unknown-007')
        ->and($event->raw_metadata)->toBe(['user' => 'unknown-007', 'direction' => 'in', 'vendor_code' => 7])
        ->and($event->occurred_at->toIso8601String())->toBe('2026-10-09T21:00:00+00:00');
    $this->assertModelExists($event);
    $this->assertDatabaseCount('biometric_device_users', 0);
    $this->assertDatabaseCount('attendance_sessions', 0);
});

test('biometric event keys deduplicate per device rather than globally', function () {
    $device = BiometricDevice::factory()->create();
    foundationEvent($device);
    $anotherDevice = BiometricDevice::factory()->create(['team_id' => $device->team_id]);
    foundationEvent($anotherDevice);

    expect(fn () => foundationEvent($device, 'scan-1', 'different-user'))->toThrow(QueryException::class);
    $this->assertDatabaseCount('biometric_events', 2);
});

test('external users map uniquely per device and only to same tenant drivers', function () {
    $device = BiometricDevice::factory()->create();
    $driver = Driver::factory()->create(['team_id' => $device->team_id]);
    $attributes = [
        'team_id' => $device->team_id,
        'biometric_device_id' => $device->id,
        'external_user_id' => '007',
        'driver_id' => $driver->id,
    ];
    $mapping = BiometricDeviceUser::query()->create($attributes);

    expect($mapping->driver()->firstOrFail()->id)->toBe($driver->id);
    expect(fn () => BiometricDeviceUser::query()->create($attributes))->toThrow(QueryException::class);
    expect(fn () => BiometricDeviceUser::query()->create(array_replace($attributes, [
        'external_user_id' => '008',
        'driver_id' => Driver::factory()->create()->id,
    ])))->toThrow(QueryException::class);
    $this->assertDatabaseCount('biometric_device_users', 1);
});

test('attendance supports open manual sessions without biometric events', function () {
    $driver = Driver::factory()->create();

    $session = AttendanceSession::query()->create([
        'team_id' => $driver->team_id,
        'driver_id' => $driver->id,
        'source' => AttendanceSource::Manual,
        'in_at' => '2026-10-09 21:00:00',
    ])->fresh();

    expect($session->source)->toBe(AttendanceSource::Manual)
        ->and($session->out_at)->toBeNull()
        ->and($session->in_event_id)->toBeNull()
        ->and($session->out_event_id)->toBeNull();
});

test('biometric attendance links same tenant evidence and keeps correction history', function () {
    $device = BiometricDevice::factory()->create();
    $driver = Driver::factory()->create(['team_id' => $device->team_id]);
    $in = foundationEvent($device, 'in-1', '007');
    $out = foundationEvent($device, 'out-1', '007');
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

    expect($session->inEvent()->firstOrFail()->id)->toBe($in->id)
        ->and($session->outEvent()->firstOrFail()->id)->toBe($out->id)
        ->and($correction->fresh()->before)->toBe(['out_at' => '2026-10-10 05:00:00'])
        ->and($correction->after)->toBe(['out_at' => '2026-10-10 04:30:00']);
    $this->assertDatabaseHas('attendance_sessions', ['id' => $session->id, 'out_at' => '2026-10-10 05:00:00']);
});

test('attendance rejects biometric evidence from another tenant', function (string $column) {
    $driver = Driver::factory()->create();
    $foreignEvent = foundationEvent(BiometricDevice::factory()->create());

    expect(fn () => AttendanceSession::query()->create([
        'team_id' => $driver->team_id,
        'driver_id' => $driver->id,
        'source' => AttendanceSource::Biometric,
        $column => $foreignEvent->id,
        'in_at' => '2026-10-09 21:00:00',
    ]))->toThrow(QueryException::class);
    $this->assertDatabaseCount('attendance_sessions', 0);
})->with(['in_event_id', 'out_event_id']);

test('audit entries retain before after and subject identifiers independently of subjects', function () {
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

    expect($entry->fresh()->subject_id)->toBe($driver->id)
        ->and($entry->before)->toBe(['is_active' => true])
        ->and($entry->after)->toBe(['is_active' => false]);
    $this->assertModelExists($entry);
    expect(fn () => $entry->update(['reason' => 'Changed history']))->toThrow(LogicException::class);
});

test('the optional demo seeder is repeatable and leaves existing master edits and ledgers alone', function () {
    $this->seed(PasadaTrackSeeder::class);
    $route = Route::query()->where('code', 'CUB-QPO')->firstOrFail();
    $route->update(['default_boundary_amount' => '1350.00']);

    $this->seed(PasadaTrackSeeder::class);

    $this->assertDatabaseCount('teams', 1);
    $this->assertDatabaseCount('drivers', 3);
    $this->assertDatabaseCount('jeepneys', 2);
    $this->assertDatabaseCount('routes', 2);
    $this->assertDatabaseCount('biometric_devices', 1);
    expect($route->fresh()->default_boundary_amount)->toBe('1350.00');
    foreach (['assignments', 'driving_records', 'boundary_obligations', 'payments', 'biometric_events', 'users', 'team_members'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

test('the demo seeder refuses production without inserting any rows', function () {
    $environment = app()->environment();
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect(fn () => (new PasadaTrackSeeder)->run())->toThrow(LogicException::class);
        $this->assertDatabaseCount('teams', 0);
    } finally {
        app()->detectEnvironment(fn (): string => $environment);
    }
});
