<?php

namespace App\Concerns;

use App\Data\TeamPermissions;
use App\Data\UserTeam;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamAccessRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasTeams
{
    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this, Membership, 'pivot'>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->using(Membership::class)
            ->withPivot(['role', 'team_access_role_id'])
            ->withTimestamps();
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return HasManyThrough<Team, Membership, $this>
     */
    public function ownedTeams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'team_id',
        )->where('team_members.role', TeamRole::Owner->value);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get the user's personal team.
     */
    public function personalTeam(): ?Team
    {
        return $this->ownedTeams()
            ->where('teams.is_personal', true)
            ->first();
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Determine if the user is the owner of the given team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $this->teamRole($team) === TeamRole::Owner;
    }

    /**
     * Get the user's role on the given team.
     */
    public function teamRole(Team $team): ?TeamRole
    {
        return $this->membershipForTeam($team)?->role;
    }

    /**
     * Get the user's teams as a collection of UserTeam objects.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false): Collection
    {
        return $this->teams()
            ->with(['memberships' => fn ($query) => $query->where('user_id', $this->id)->with('accessRole')])
            ->get()
            ->map(fn (Team $team) => ! $includeCurrent && $this->isCurrentTeam($team) ? null : $this->toUserTeam($team))
            ->filter()
            ->values();
    }

    /**
     * Get the user's team as a UserTeam object.
     */
    public function toUserTeam(Team $team): UserTeam
    {
        $membership = $this->membershipForTeam($team);
        $role = $membership?->role;
        $accessRole = $membership?->accessRole;

        return new UserTeam(
            id: $team->id,
            name: $team->name,
            slug: $team->slug,
            isPersonal: $team->is_personal,
            role: $accessRole->key ?? $role?->value,
            roleLabel: $accessRole->name ?? $role?->label(),
            isCurrent: $this->isCurrentTeam($team),
        );
    }

    /**
     * Get the standard permissions for a team as a TeamPermissions object.
     */
    public function toTeamPermissions(Team $team): TeamPermissions
    {
        $role = $this->teamAccessRole($team);

        return new TeamPermissions(
            canUpdateTeam: $role?->allows(TeamPermission::UpdateTeam) ?? false,
            canDeleteTeam: $role?->allows(TeamPermission::DeleteTeam) ?? false,
            canAddMember: $role?->allows(TeamPermission::AddMember) ?? false,
            canUpdateMember: $role?->allows(TeamPermission::UpdateMember) ?? false,
            canRemoveMember: $role?->allows(TeamPermission::RemoveMember) ?? false,
            canCreateInvitation: $role?->allows(TeamPermission::CreateInvitation) ?? false,
            canCancelInvitation: $role?->allows(TeamPermission::CancelInvitation) ?? false,
        );
    }

    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(teams.name)')
            ->first();
    }

    /**
     * Determine if the user has the given permission on the team.
     */
    public function hasTeamPermission(Team $team, TeamPermission $permission): bool
    {
        return $this->isActive() && ($this->teamAccessRole($team)?->allows($permission) ?? false);
    }

    public function teamAccessRole(Team $team): ?TeamAccessRole
    {
        return $this->membershipForTeam($team)?->accessRole;
    }

    private function membershipForTeam(Team $team): ?Membership
    {
        if ($this->relationLoaded('teamMemberships')) {
            return $this->teamMemberships->firstWhere('team_id', $team->id);
        }
        if ($team->relationLoaded('memberships')) {
            return $team->memberships->firstWhere('user_id', $this->id);
        }

        return $this->teamMemberships()->with('accessRole')->where('team_id', $team->id)->first();
    }

    public function canAssignTeamRole(Team $team, TeamAccessRole $role): bool
    {
        $ownRole = $this->teamAccessRole($team);

        return $this->isActive() && $ownRole !== null && ! $role->is_archived && ! $role->is_owner
            && ($ownRole->is_owner || array_diff($role->permissions, $ownRole->permissions) === []);
    }

    /** @return array<array{value: string, label: string}> */
    public function assignableTeamRoles(Team $team): array
    {
        return TeamAccessRole::query()->where('is_archived', false)->where('is_owner', false)->orderBy('name')->get()
            ->filter(fn (TeamAccessRole $role): bool => $this->canAssignTeamRole($team, $role))
            ->map(fn (TeamAccessRole $role): array => ['value' => $role->key, 'label' => $role->name])->values()->all();
    }
}
