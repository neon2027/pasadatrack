<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountStatus;
use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'account_status' => ['required', Rule::enum(AccountStatus::class)],
            'privilege_level_id' => ['required', 'integer', 'exists:privilege_levels,id'],
            'platform_role' => ['sometimes', Rule::enum(PlatformRole::class)], 'reason' => ['required', 'string', 'max:1000']];
    }
}
