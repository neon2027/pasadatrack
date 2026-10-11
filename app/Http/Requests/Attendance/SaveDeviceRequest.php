<?php

namespace App\Http\Requests\Attendance;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;
use Illuminate\Validation\Rule;

class SaveDeviceRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageDevices;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('biometric_devices')->where('team_id', $this->team()->id)->ignore($this->route('device'))],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
