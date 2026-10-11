<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\OperationsRequest;
use Illuminate\Validation\Rule;

class ImportBiometricEventsRequest extends OperationsRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:500'],
            'events.*' => ['array:event_key,external_user_id,occurred_at,direction'],
            'events.*.event_key' => ['required', 'string', 'max:100', 'distinct'],
            'events.*.external_user_id' => ['required', 'string', 'max:100'],
            'events.*.occurred_at' => ['required', 'date_format:Y-m-d\\TH:i:sP'],
            'events.*.direction' => ['required', Rule::in(['in', 'out'])],
        ];
    }
}
