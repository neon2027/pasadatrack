<?php

namespace App\Models\Attendance;

use App\Enums\Attendance\AttendanceSource;
use App\Models\Fleet\Driver;
use App\Models\Fleet\TeamScopedModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $driver_id
 * @property AttendanceSource $source
 * @property int|null $in_event_id
 * @property int|null $out_event_id
 * @property CarbonImmutable $in_at
 * @property CarbonImmutable|null $out_at
 */
#[Fillable(['team_id', 'driver_id', 'source', 'in_event_id', 'out_event_id', 'in_at', 'out_at'])]
class AttendanceSession extends TeamScopedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['source' => AttendanceSource::class, 'in_at' => 'immutable_datetime', 'out_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return BelongsTo<BiometricEvent, $this> */
    public function inEvent(): BelongsTo
    {
        return $this->belongsTo(BiometricEvent::class, 'in_event_id');
    }

    /** @return BelongsTo<BiometricEvent, $this> */
    public function outEvent(): BelongsTo
    {
        return $this->belongsTo(BiometricEvent::class, 'out_event_id');
    }

    /** @return HasMany<AttendanceCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }
}
