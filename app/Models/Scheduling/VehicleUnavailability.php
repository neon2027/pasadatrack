<?php

namespace App\Models\Scheduling;

use App\Models\Fleet\Jeepney;
use App\Models\Fleet\TeamScopedModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $jeepney_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $reason
 */
#[Fillable(['team_id', 'jeepney_id', 'starts_at', 'ends_at', 'reason'])]
class VehicleUnavailability extends TeamScopedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Jeepney, $this> */
    public function jeepney(): BelongsTo
    {
        return $this->belongsTo(Jeepney::class);
    }
}
