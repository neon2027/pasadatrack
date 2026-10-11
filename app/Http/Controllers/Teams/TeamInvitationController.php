<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Admin\ManageUsers;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamInvitationRequest;
use App\Http\Requests\Teams\RespondToTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamAccessRole;
use App\Models\TeamInvitation;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class TeamInvitationController extends Controller
{
    /**
     * Store a newly created invitation.
     */
    public function store(CreateTeamInvitationRequest $request, Team $team, ManageUsers $users): RedirectResponse
    {
        $invitation = DB::transaction(function () use ($request, $team, $users): TeamInvitation {
            $users->lockSecurity();
            $actor = $request->user()->fresh();
            Gate::forUser($actor)->authorize('inviteMember', $team);
            $role = TeamAccessRole::query()->where('key', $request->validated('role'))->firstOrFail();
            abort_unless($actor->canAssignTeamRole($team, $role), 403);

            return $team->invitations()->create([
                'email' => $request->validated('email'), 'role' => TeamRole::tryFrom($role->key) ?? TeamRole::Member,
                'team_access_role_id' => $role->id, 'invited_by' => $actor->id, 'expires_at' => now()->addDays(3),
            ]);
        }, 3);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Cancel the specified invitation.
     */
    public function destroy(Team $team, TeamInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->team_id === $team->id, 404);

        Gate::authorize('cancelInvitation', $team);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Accept the invitation.
     */
    public function accept(RespondToTeamInvitationRequest $request, TeamInvitation $invitation, ManageUsers $users): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $invitation, $users) {
            $users->lockSecurity();
            $invitation = TeamInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            abort_unless($invitation->isPending(), 403);
            abort_unless($user->fresh()->isActive() && $invitation->accessRole && ! $invitation->accessRole->is_archived && ! $invitation->accessRole->is_owner, 403);
            $team = $invitation->team;

            $team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role, 'team_access_role_id' => $invitation->team_access_role_id],
            );

            $invitation->update(['accepted_at' => now()]);

            $user->switchTeam($team);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation accepted.')]);

        return to_route('dashboard');
    }

    /**
     * Decline the invitation.
     */
    public function decline(RespondToTeamInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return to_route('dashboard');
    }
}
