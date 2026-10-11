<?php

namespace App\Actions\Attendance;

use App\Actions\Audit\RecordAuditEntry;
use App\Data\OperationsTime;
use App\Enums\Attendance\AttendanceSource;
use App\Models\Attendance\AttendanceCorrection;
use App\Models\Attendance\AttendanceSession;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageAttendance
{
    public function __construct(private RecordAuditEntry $audit) {}

    /** @param array<string, mixed> $data */
    public function save(Team $team, User $actor, array $data, ?AttendanceSession $session = null): void
    {
        DB::transaction(function () use ($team, $actor, $data, $session): void {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $session = $session ? AttendanceSession::forTeam($team)->lockForUpdate()->findOrFail($session->id) : new AttendanceSession;
            $start = OperationsTime::utc($data['in_at']);
            $end = empty($data['out_at']) ? null : OperationsTime::utc($data['out_at']);
            $this->ensureNoOverlap($team, (int) $data['driver_id'], $start, $end, $session->id);
            $before = $session->exists ? $session->attributesToArray() : null;
            $session->fill([
                'team_id' => $team->id, 'driver_id' => $data['driver_id'], 'source' => AttendanceSource::Manual,
                'in_at' => $start, 'out_at' => $end,
            ])->save();
            if ($before !== null) {
                AttendanceCorrection::query()->create([
                    'team_id' => $team->id, 'attendance_session_id' => $session->id,
                    'reason' => $data['reason'], 'corrected_by' => $actor->id, 'corrected_at' => now('UTC'),
                    'before' => $before, 'after' => $session->attributesToArray(),
                ]);
            }
            $this->audit->handle($actor, $session, $before ? 'attendance.corrected' : 'attendance.manual', $before, $data['reason']);
        }, 5);
    }

    public function ensureNoOverlap(Team $team, int $driverId, CarbonImmutable $start, ?CarbonImmutable $end, ?int $except = null): void
    {
        $query = AttendanceSession::forTeam($team)->where('driver_id', $driverId)
            ->when($except, fn ($query) => $query->whereKeyNot($except))
            ->where(fn ($query) => $query->whereNull('out_at')->orWhere('out_at', '>', $start));
        if ($end !== null) {
            $query->where('in_at', '<', $end);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['in_at' => 'This driver has an overlapping or open attendance session.']);
        }
    }
}
