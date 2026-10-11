<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\OperationsRequest;

class RevokeEnrollmentRequest extends OperationsRequest
{
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
