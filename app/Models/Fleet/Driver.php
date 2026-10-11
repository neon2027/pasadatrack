<?php

namespace App\Models\Fleet;

use App\Models\Attendance\AttendanceSession;
use App\Models\Attendance\BiometricDeviceUser;
use App\Models\Boundary\BoundaryObligation;
use App\Models\Boundary\Payment;
use App\Models\Scheduling\Assignment;
use App\Models\Scheduling\DrivingRecord;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 * @property string $name
 * @property string|null $license_number
 * @property string|null $phone
 * @property bool $is_active
 */
#[Fillable(['team_id', 'code', 'name', 'license_number', 'phone', 'is_active'])]
#[UseFactory(DriverFactory::class)]
class Driver extends TeamScopedModel
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'planned_driver_id');
    }

    /** @return HasMany<DrivingRecord, $this> */
    public function drivingRecords(): HasMany
    {
        return $this->hasMany(DrivingRecord::class, 'actual_driver_id');
    }

    /** @return HasMany<BoundaryObligation, $this> */
    public function boundaryObligations(): HasMany
    {
        return $this->hasMany(BoundaryObligation::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<BiometricDeviceUser, $this> */
    public function biometricDeviceUsers(): HasMany
    {
        return $this->hasMany(BiometricDeviceUser::class);
    }

    /** @return HasMany<AttendanceSession, $this> */
    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
