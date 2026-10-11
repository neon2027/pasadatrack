<?php

namespace App\Http\Requests;

use App\Enums\TeamPermission;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

abstract class OperationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasTeamPermission($this->team(), $this->permission()) ?? false;
    }

    abstract protected function permission(): TeamPermission;

    public function team(): Team
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);

        return $team;
    }

    protected function teamExists(string $table, bool $active = false): Exists
    {
        $rule = Rule::exists($table, 'id')->where('team_id', $this->team()->id);

        return $active ? $rule->where('is_active', true) : $rule;
    }

    /** @return array<string> */
    protected function moneyRules(bool $signed = false): array
    {
        return ['required', 'regex:/^'.($signed ? '-?' : '').'\d{1,10}(\.\d{1,2})?$/'];
    }
}
