<?php

namespace App\Http\Requests\Fleet;

use App\Enums\TeamPermission;
use App\Http\Requests\OperationsRequest;
use Illuminate\Validation\Rule;

class SaveRouteRequest extends OperationsRequest
{
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageFleet;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('routes')->where('team_id', $this->team()->id)->ignore($this->route('route'))],
            'name' => ['required', 'string', 'max:255'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'default_boundary_amount' => $this->moneyRules(),
            'is_active' => ['required', 'boolean'],
        ];
    }
}
