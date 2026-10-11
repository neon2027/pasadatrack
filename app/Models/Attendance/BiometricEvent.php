<?php

namespace App\Models\Attendance;

use App\Models\Boundary\AppendOnlyModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Raw evidence is preserved even without a device-user mapping. Normalized imports
 * pair explicit in/out scans; ambiguous evidence remains available for review.
 *
 * @property int $biometric_device_id
 * @property string $event_key
 * @property string $external_user_id
 * @property array<string, mixed> $raw_metadata
 * @property CarbonImmutable $occurred_at
 * @property-read bool $in_sessions_exists
 * @property-read bool $out_sessions_exists
 */
#[Fillable(['team_id', 'biometric_device_id', 'event_key', 'external_user_id', 'raw_metadata', 'occurred_at'])]
class BiometricEvent extends AppendOnlyModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['raw_metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<BiometricDevice, $this> */
    public function biometricDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    /** @return HasMany<AttendanceSession, $this> */
    public function inSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'in_event_id');
    }

    /** @return HasMany<AttendanceSession, $this> */
    public function outSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'out_event_id');
    }
}
