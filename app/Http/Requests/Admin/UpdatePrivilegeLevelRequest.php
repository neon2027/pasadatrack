<?php

namespace App\Http\Requests\Admin;

use App\Models\PrivilegeLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrivilegeLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform-catalog') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $level = $this->route('level');

        return ['name' => ['required', 'string', 'max:100', Rule::unique('privilege_levels')->ignore($level instanceof PrivilegeLevel ? $level->id : null)],
            'team_limit' => ['required', 'integer', 'min:1', 'max:2147483647'], 'reason' => ['required', 'string', 'max:1000']];
    }
}
