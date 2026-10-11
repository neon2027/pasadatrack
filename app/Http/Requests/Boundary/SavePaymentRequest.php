<?php

namespace App\Http\Requests\Boundary;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;
use Illuminate\Validation\Rule;

class SavePaymentRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageCollections;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', $this->teamExists('drivers')],
            'amount' => $this->moneyRules(),
            'collected_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'receipt_number' => ['required', 'string', 'max:100', Rule::unique('payments')->where('team_id', $this->team()->id)],
        ];
    }
}
