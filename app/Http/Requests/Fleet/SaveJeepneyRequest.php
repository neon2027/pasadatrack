<?php

namespace App\Http\Requests\Fleet;

use App\Http\Requests\OperationsRequest;
use Illuminate\Validation\Rule;

class SaveJeepneyRequest extends OperationsRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plate_number' => ['required', 'string', 'max:30', Rule::unique('jeepneys')->where('team_id', $this->team()->id)->ignore($this->route('jeepney'))],
            'name' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
