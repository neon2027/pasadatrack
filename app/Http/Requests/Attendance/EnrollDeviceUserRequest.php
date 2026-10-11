<?php

namespace App\Http\Requests\Attendance;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;
use App\Models\Attendance\BiometricDevice;
use Illuminate\Validation\Rule;

class EnrollDeviceUserRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageDevices;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $device = $this->route('device');
        abort_unless($device instanceof BiometricDevice, 404);

        return [
            'driver_id' => ['required', 'integer', $this->teamExists('drivers', true)],
            'external_user_id' => ['required', 'string', 'max:100', Rule::unique('biometric_device_users')->where('biometric_device_id', $device->id)],
        ];
    }
}
