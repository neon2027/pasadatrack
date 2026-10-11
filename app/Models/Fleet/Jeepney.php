<?php

namespace App\Models\Fleet;

use App\Models\Scheduling\Assignment;
use App\Models\Scheduling\VehicleUnavailability;
use Database\Factories\JeepneyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $plate_number
 * @property string|null $name
 * @property int|null $capacity
 * @property bool $is_active
 */
#[Fillable(['team_id', 'plate_number', 'name', 'capacity', 'is_active'])]
#[UseFactory(JeepneyFactory::class)]
class Jeepney extends TeamScopedModel
{
    /** @use HasFactory<JeepneyFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'capacity' => 'integer'];
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return HasMany<VehicleUnavailability, $this> */
    public function unavailabilities(): HasMany
    {
        return $this->hasMany(VehicleUnavailability::class);
    }
}
