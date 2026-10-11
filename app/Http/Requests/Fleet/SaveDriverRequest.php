<?php

namespace App\Http\Requests\Fleet;

use App\Http\Requests\OperationsRequest;
use Illuminate\Validation\Rule;

class SaveDriverRequest extends OperationsRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('drivers')->where('team_id', $this->team()->id)->ignore($this->route('driver'))],
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
