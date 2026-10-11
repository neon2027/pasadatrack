<?php

namespace App\Http\Requests\Boundary;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;

class ReversePaymentRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ReversePayment;
    }

    public function authorize(): bool
    {
        return $this->user()?->hasTeamPermission($this->team(), TeamPermission::ReversePayment) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
