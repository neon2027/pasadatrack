<?php

namespace App\Http\Requests\Admin;

use App\Enums\TeamPermission;
use App\Models\TeamAccessRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTeamAccessRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform-catalog') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->route('role');

        return ['name' => ['required', 'string', 'max:100', Rule::unique('team_access_roles')->ignore($role instanceof TeamAccessRole ? $role->id : null)],
            'permissions' => ['present', 'array'], 'permissions.*' => ['required', 'string', 'distinct', Rule::in(TeamPermission::catalog())],
            'is_archived' => ['sometimes', 'boolean'], 'reason' => ['required', 'string', 'max:1000']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $permissions = $this->input('permissions', []);
            if (! is_array($permissions)) {
                return;
            }
            foreach (TeamPermission::dependencies() as $permission => $required) {
                if (in_array($permission, $permissions, true) && ! in_array($required, $permissions, true)) {
                    $validator->errors()->add('permissions', __(':permission requires :required.', ['permission' => $permission, 'required' => $required]));
                }
            }
        });
    }
}
