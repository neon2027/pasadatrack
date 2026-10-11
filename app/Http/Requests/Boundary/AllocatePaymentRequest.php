<?php

namespace App\Http\Requests\Boundary;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;

class AllocatePaymentRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageCollections;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'boundary_obligation_id' => ['required', 'integer', $this->teamExists('boundary_obligations')],
            'amount' => $this->moneyRules(),
        ];
    }
}
