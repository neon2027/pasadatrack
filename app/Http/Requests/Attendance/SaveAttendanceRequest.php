<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\OperationsRequest;

class SaveAttendanceRequest extends OperationsRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', $this->teamExists('drivers')],
            'in_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'out_at' => ['nullable', 'date_format:Y-m-d\\TH:i', 'after:in_at'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
