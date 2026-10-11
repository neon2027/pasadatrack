<?php

namespace App\Models\Scheduling;

use App\Models\Boundary\AppendOnlyModel;
use App\Models\Boundary\BoundaryObligation;
use App\Models\Fleet\Driver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A confirmed fact, not a draft. Services atomically confirm the assignment and
 * insert this snapshot; changing route prices never recalculates historical rows.
 *
 * @property int $assignment_id
 * @property int $actual_driver_id
 * @property int $confirmed_by
 * @property CarbonImmutable $confirmed_at
 * @property string $boundary_amount
 * @property CarbonImmutable $driving_date
 * @property-read Assignment $assignment
 * @property-read Driver $actualDriver
 * @property-read BoundaryObligation|null $boundaryObligation
 */
#[Fillable(['team_id', 'assignment_id', 'actual_driver_id', 'confirmed_by', 'confirmed_at', 'boundary_amount', 'driving_date'])]
class DrivingRecord extends AppendOnlyModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'immutable_datetime',
            'driving_date' => 'immutable_date',
            'boundary_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function actualDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'actual_driver_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return HasOne<BoundaryObligation, $this> */
    public function boundaryObligation(): HasOne
    {
        return $this->hasOne(BoundaryObligation::class);
    }
}
