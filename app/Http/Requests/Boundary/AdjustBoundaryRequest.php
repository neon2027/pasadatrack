<?php

namespace App\Http\Requests\Boundary;

use App\Http\Requests\OperationsRequest;

class AdjustBoundaryRequest extends OperationsRequest
{
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
