<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;

class SaveUnavailabilityRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageScheduling;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'jeepney_id' => ['required', 'integer', $this->teamExists('jeepneys')],
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
