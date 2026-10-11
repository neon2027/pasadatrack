<?php

namespace App\Models\Attendance;

use App\Models\Boundary\AppendOnlyModel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $attendance_session_id
 * @property string $reason
 * @property int $corrected_by
 * @property array<string, mixed> $before
 * @property array<string, mixed> $after
 * @property CarbonImmutable $corrected_at
 */
#[Fillable(['team_id', 'attendance_session_id', 'reason', 'corrected_by', 'before', 'after', 'corrected_at'])]
class AttendanceCorrection extends AppendOnlyModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'corrected_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AttendanceSession, $this> */
    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
