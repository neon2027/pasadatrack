<?php

namespace App\Http\Requests\Attendance;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;

class RevokeEnrollmentRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageDevices;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
