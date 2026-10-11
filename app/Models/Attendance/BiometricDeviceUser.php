<?php

namespace App\Models\Attendance;

use App\Models\Fleet\Driver;
use App\Models\Fleet\TeamScopedModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $biometric_device_id
 * @property string $external_user_id
 * @property int $driver_id
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['team_id', 'biometric_device_id', 'external_user_id', 'driver_id', 'revoked_at'])]
class BiometricDeviceUser extends TeamScopedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['revoked_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<BiometricDevice, $this> */
    public function biometricDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
