<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\PrivilegeLevel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner.
     */
    public function handle(User $user, string $name, bool $isPersonal = false): Team
    {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            DB::table('users')->where('id', $user->id)->increment('auth_version', 0);
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $level = PrivilegeLevel::query()->lockForUpdate()->findOrFail($owner->privilege_level_id);
            if (! $owner->isActive() || $owner->ownedTeams()->count() >= $level->team_limit) {
                throw ValidationException::withMessages(['name' => __('Your :level level allows :limit owned teams, including your personal team. Ask an administrator to upgrade your level.', ['level' => $level->name, 'limit' => $level->team_limit])]);
            }
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $membership = $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            $user->switchTeam($team);

            return $team;
        }, 5);
    }
}
