<?php

namespace App\Models\Attendance;

use App\Models\Fleet\TeamScopedModel;
use Database\Factories\BiometricDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 * @property string $name
 * @property array<string, mixed>|null $metadata
 * @property bool $is_active
 */
#[Fillable(['team_id', 'code', 'name', 'metadata', 'is_active'])]
#[UseFactory(BiometricDeviceFactory::class)]
class BiometricDevice extends TeamScopedModel
{
    /** @use HasFactory<BiometricDeviceFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array', 'is_active' => 'boolean'];
    }

    /** @return HasMany<BiometricDeviceUser, $this> */
    public function deviceUsers(): HasMany
    {
        return $this->hasMany(BiometricDeviceUser::class);
    }

    /** @return HasMany<BiometricEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BiometricEvent::class);
    }
}
