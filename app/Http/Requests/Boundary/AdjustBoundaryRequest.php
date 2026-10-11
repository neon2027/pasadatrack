<?php

namespace App\Http\Requests\Boundary;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;

class AdjustBoundaryRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageCollections;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => $this->moneyRules(true),
            'reason' => ['required', 'string', 'max:2000'],
            'submission_id' => ['required', 'uuid'],
        ];
    }
}
