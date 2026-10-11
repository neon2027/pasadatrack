<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;

class ConfirmDrivingRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageScheduling;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'actual_driver_id' => ['required', 'integer', $this->teamExists('drivers', true)],
            'expected_boundary_amount' => $this->moneyRules(),
        ];
    }
}
