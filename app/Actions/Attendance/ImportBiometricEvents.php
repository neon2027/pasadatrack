<?php

namespace App\Actions\Attendance;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\Attendance\AttendanceSource;
use App\Models\Attendance\AttendanceSession;
use App\Models\Attendance\BiometricDevice;
use App\Models\Attendance\BiometricDeviceUser;
use App\Models\Attendance\BiometricEvent;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportBiometricEvents
{
    public function __construct(private RecordAuditEntry $audit, private ManageAttendance $attendance) {}

    /** @param array<int, array<string, string>> $events */
    public function handle(Team $team, User $actor, BiometricDevice $device, array $events): void
    {
        DB::transaction(function () use ($team, $actor, $device, $events): void {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $device = BiometricDevice::forTeam($team)->where('is_active', true)->findOrFail($device->id);
            foreach ($events as $input) {
                $instant = CarbonImmutable::parse($input['occurred_at'])->utc();
                $event = $device->events()->where('event_key', $input['event_key'])->first();
                if ($event) {
                    if ($event->external_user_id !== $input['external_user_id'] || ! $event->occurred_at->equalTo($instant) || ($event->raw_metadata['direction'] ?? null) !== $input['direction']) {
                        throw ValidationException::withMessages(['events' => 'An existing event key has different scan data. Use the original scan identifier and values.']);
                    }

                    continue;
                }
                $event = BiometricEvent::query()->create([
                    'team_id' => $team->id, 'biometric_device_id' => $device->id,
                    'event_key' => $input['event_key'], 'external_user_id' => $input['external_user_id'],
                    'occurred_at' => $instant, 'raw_metadata' => $input,
                ]);
                $this->audit->handle($actor, $event, 'biometric.imported');
            }
            $this->processPending($team, $actor, $device);
        }, 5);
    }

    public function processPending(Team $team, User $actor, BiometricDevice $device): void
    {
        DB::transaction(function () use ($team, $actor, $device): void {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            abort_unless($device->team_id === $team->id && $device->is_active, 404);
            $events = $device->events()->whereDoesntHave('inSessions')->whereDoesntHave('outSessions')
                ->orderBy('occurred_at')->orderBy('id')->cursor();
            foreach ($events as $event) {
                $mapping = BiometricDeviceUser::forTeam($team)->where('biometric_device_id', $device->id)
                    ->where('external_user_id', $event->external_user_id)->whereHas('driver', fn ($query) => $query->where('is_active', true))->first();
                if (! $mapping) {
                    continue;
                }
                if ($mapping->revoked_at !== null && $event->occurred_at->greaterThanOrEqualTo($mapping->revoked_at)) {
                    continue;
                }
                $direction = $event->raw_metadata['direction'] ?? null;
                if ($direction === 'in') {
                    try {
                        $this->attendance->ensureNoOverlap($team, $mapping->driver_id, $event->occurred_at, null);
                    } catch (ValidationException) {
                        continue;
                    }
                    $session = AttendanceSession::query()->create([
                        'team_id' => $team->id, 'driver_id' => $mapping->driver_id,
                        'source' => AttendanceSource::Biometric, 'in_event_id' => $event->id,
                        'in_at' => $event->occurred_at,
                    ]);
                    $this->audit->handle($actor, $session, 'attendance.biometric-in');
                } elseif ($direction === 'out') {
                    $session = AttendanceSession::forTeam($team)->where('driver_id', $mapping->driver_id)
                        ->where('source', AttendanceSource::Biometric)->whereNull('out_at')->where('in_at', '<', $event->occurred_at)
                        ->whereHas('inEvent', fn ($query) => $query->where('biometric_device_id', $device->id))->latest('in_at')->first();
                    if (! $session) {
                        continue;
                    }
                    $before = $session->attributesToArray();
                    $session->update(['out_event_id' => $event->id, 'out_at' => $event->occurred_at]);
                    $this->audit->handle($actor, $session, 'attendance.biometric-out', $before);
                }
            }
        }, 5);
    }
}
