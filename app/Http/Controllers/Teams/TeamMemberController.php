<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Admin\ManageUsers;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\TeamAccessRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user, ManageUsers $users): RedirectResponse
    {
        DB::transaction(function () use ($request, $team, $user, $users): void {
            $users->lockSecurity();
            $actor = $request->user()->fresh();
            Gate::forUser($actor)->authorize('updateMember', $team);
            $newRole = TeamAccessRole::query()->where('key', $request->validated('role'))->firstOrFail();
            abort_if($team->owner()?->is($user), 403, __('The team owner cannot be changed.'));
            abort_unless($actor->canAssignTeamRole($team, $newRole), 403);
            $existingRole = $user->teamAccessRole($team);
            $actorRole = $actor->teamAccessRole($team);
            abort_unless($existingRole !== null, 404);
            abort_unless($actorRole && ($actorRole->is_owner || array_diff($existingRole->permissions, $actorRole->permissions) === []), 403);
            $team->memberships()->where('user_id', $user->id)->firstOrFail()->update(['role' => TeamRole::tryFrom($newRole->key) ?? TeamRole::Member, 'team_access_role_id' => $newRole->id]);
        }, 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    public function destroy(Request $request, Team $team, User $user, ManageUsers $users): RedirectResponse
    {
        DB::transaction(function () use ($request, $team, $user, $users): void {
            $users->lockSecurity();
            $actor = $request->user()->fresh();
            Gate::forUser($actor)->authorize('removeMember', $team);
            abort_if($team->owner()?->is($user), 403, __('The team owner cannot be removed.'));
            $targetRole = $user->teamAccessRole($team);
            $actorRole = $actor->teamAccessRole($team);
            abort_unless($targetRole !== null, 404);
            abort_unless($actorRole && ($actorRole->is_owner || array_diff($targetRole->permissions, $actorRole->permissions) === []), 403);
            $team->memberships()->where('user_id', $user->id)->delete();
            if ($user->isCurrentTeam($team) && ($personalTeam = $user->personalTeam())) {
                $user->switchTeam($personalTeam);
            }
        }, 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
