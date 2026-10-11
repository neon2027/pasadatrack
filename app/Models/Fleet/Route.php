<?php

namespace App\Models\Fleet;

use App\Models\Scheduling\Assignment;
use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 * @property string $name
 * @property string $origin
 * @property string $destination
 * @property string $default_boundary_amount
 * @property bool $is_active
 */
#[Fillable(['team_id', 'code', 'name', 'origin', 'destination', 'default_boundary_amount', 'is_active'])]
#[UseFactory(RouteFactory::class)]
class Route extends TeamScopedModel
{
    /** @use HasFactory<RouteFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'default_boundary_amount' => 'decimal:2'];
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
