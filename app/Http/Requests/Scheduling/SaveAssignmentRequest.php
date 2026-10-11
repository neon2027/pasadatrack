<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;

class SaveAssignmentRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageScheduling;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'planned_driver_id' => ['required', 'integer', $this->teamExists('drivers', true)],
            'jeepney_id' => ['required', 'integer', $this->teamExists('jeepneys', true)],
            'route_id' => ['required', 'integer', $this->teamExists('routes', true)],
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:starts_at'],
        ];
    }
}
