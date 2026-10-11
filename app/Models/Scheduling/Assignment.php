<?php

namespace App\Models\Scheduling;

use App\Enums\Scheduling\AssignmentStatus;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Jeepney;
use App\Models\Fleet\Route;
use App\Models\Fleet\TeamScopedModel;
use Carbon\CarbonImmutable;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Services normalize UTC inputs, validate date ranges, driver/vehicle overlaps and
 * vehicle availability, and apply AssignmentStatus::canTransitionTo under a lock.
 * Confirmation and its record/snapshot must be atomic; cancelled assignments must
 * never produce a record. Confirmed scheduling details must not be rewritten.
 *
 * @property int $planned_driver_id
 * @property int $jeepney_id
 * @property int $route_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $operational_date
 * @property AssignmentStatus $status
 * @property-read Driver $plannedDriver
 * @property-read Jeepney $jeepney
 * @property-read Route $route
 * @property-read DrivingRecord|null $drivingRecord
 */
#[Fillable(['team_id', 'planned_driver_id', 'jeepney_id', 'route_id', 'starts_at', 'ends_at', 'operational_date', 'status'])]
#[UseFactory(AssignmentFactory::class)]
class Assignment extends TeamScopedModel
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'operational_date' => 'immutable_date',
            'status' => AssignmentStatus::class,
        ];
    }

    /** @return BelongsTo<Driver, $this> */
    public function plannedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'planned_driver_id');
    }

    /** @return BelongsTo<Jeepney, $this> */
    public function jeepney(): BelongsTo
    {
        return $this->belongsTo(Jeepney::class);
    }

    /** @return BelongsTo<Route, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /** @return HasOne<DrivingRecord, $this> */
    public function drivingRecord(): HasOne
    {
        return $this->hasOne(DrivingRecord::class);
    }
}
