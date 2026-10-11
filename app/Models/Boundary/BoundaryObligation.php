<?php

namespace App\Models\Boundary;

use App\Models\Fleet\Driver;
use App\Models\Scheduling\DrivingRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Services copy the confirmed record's boundary_amount into original_amount once.
 * The schema enforces record/driver ownership, not amount equality or balances.
 * Amount range/scale validation is required, especially on SQLite's numeric affinity.
 *
 * @property int $driving_record_id
 * @property int $driver_id
 * @property string $original_amount
 * @property-read DrivingRecord $drivingRecord
 * @property-read Driver $driver
 */
#[Fillable(['team_id', 'driving_record_id', 'driver_id', 'original_amount'])]
class BoundaryObligation extends AppendOnlyModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['original_amount' => 'decimal:2'];
    }

    /** @return BelongsTo<DrivingRecord, $this> */
    public function drivingRecord(): BelongsTo
    {
        return $this->belongsTo(DrivingRecord::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return HasMany<BoundaryAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(BoundaryAdjustment::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
