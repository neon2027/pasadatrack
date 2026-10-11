<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProvisionUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'privilege_level_id' => ['required', 'integer', 'exists:privilege_levels,id'],
            'platform_role' => ['sometimes', Rule::enum(PlatformRole::class)], 'reason' => ['required', 'string', 'max:1000']];
    }
}
