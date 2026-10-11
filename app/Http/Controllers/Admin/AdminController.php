<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ManageUsers;
use App\Actions\Admin\RecordPlatformAudit;
use App\Enums\PlatformRole;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProvisionUserRequest;
use App\Http\Requests\Admin\SaveTeamAccessRoleRequest;
use App\Http\Requests\Admin\UpdateManagedUserRequest;
use App\Http\Requests\Admin\UpdatePrivilegeLevelRequest;
use App\Models\PlatformAuditEntry;
use App\Models\PrivilegeLevel;
use App\Models\TeamAccessRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function users(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,suspended'],
            'role' => ['nullable', 'in:user,admin,super-admin'], 'level' => ['nullable', 'integer', 'exists:privilege_levels,id']]);
        $super = $request->user()->platform_role === PlatformRole::SuperAdmin;
        $users = User::query()->with(['privilegeLevel', 'teamMemberships.team', 'teamMemberships.accessRole'])->withCount('ownedTeams')
            ->when(! $super, fn ($query) => $query->where('platform_role', PlatformRole::User))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('account_status', $status))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('platform_role', $role))
            ->when($filters['level'] ?? null, fn ($query, $level) => $query->where('privilege_level_id', $level))
            ->orderBy('name')->orderBy('id')->paginate(20)->withQueryString()->through(fn (User $user): array => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'platform_role' => $user->platform_role->value, 'account_status' => $user->account_status->value,
                'privilege_level_id' => $user->privilege_level_id, 'level' => $user->privilegeLevel->name,
                'team_limit' => $user->privilegeLevel->team_limit, 'owned_teams_count' => $user->getAttribute('owned_teams_count'),
                'google_connected' => $user->google_id !== null, 'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'memberships' => $user->teamMemberships->filter(fn ($membership) => $membership->team !== null)->map(fn ($membership): array => [
                    'team' => $membership->team->name, 'role' => $membership->accessRole->name ?? $membership->role->label(),
                ])->values(),
            ]);

        return Inertia::render('admin/users', ['users' => $users, 'levels' => PrivilegeLevel::query()->orderBy('team_limit')->get(), 'filters' => $filters]);
    }

    public function provision(ProvisionUserRequest $request, ManageUsers $users): RedirectResponse
    {
        $users->provision($request->user(), ['name' => $request->validated('name'), 'email' => $request->validated('email'),
            'privilege_level_id' => $request->validated('privilege_level_id'), 'platform_role' => $request->validated('platform_role', 'user'), 'reason' => $request->validated('reason')]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'User provisioned. They can now start Google sign-in.']);

        return to_route('admin.users.index');
    }

    public function updateUser(UpdateManagedUserRequest $request, User $user, ManageUsers $users): RedirectResponse
    {
        $users->update($request->user(), $user, ['name' => $request->validated('name'), 'account_status' => $request->validated('account_status'),
            'privilege_level_id' => $request->validated('privilege_level_id'), 'platform_role' => $request->validated('platform_role', $user->platform_role->value), 'reason' => $request->validated('reason')]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'User access updated. Existing teams and data are preserved.']);

        return back();
    }

    public function roles(): Response
    {
        Gate::authorize('manage-platform-catalog');

        return Inertia::render('admin/roles', ['roles' => TeamAccessRole::query()->orderBy('id')->get(), 'catalog' => TeamPermission::catalog(), 'dependencies' => TeamPermission::dependencies()]);
    }

    public function storeRole(SaveTeamAccessRoleRequest $request, RecordPlatformAudit $audit, ManageUsers $users): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit, $users): void {
            $users->lockSecurity();
            Gate::authorize('manage-platform-catalog');
            $role = TeamAccessRole::query()->create(['key' => 'custom-'.Str::uuid(), 'name' => $request->validated('name'), 'permissions' => $request->validated('permissions')]);
            $audit->handle($request->user(), $role, 'role.created', null, $role->only(['key', 'name', 'permissions', 'is_archived']), $request->validated('reason'));
        }, 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Team role created.']);

        return back();
    }

    public function updateRole(SaveTeamAccessRoleRequest $request, TeamAccessRole $role, RecordPlatformAudit $audit, ManageUsers $users): RedirectResponse
    {
        DB::transaction(function () use ($request, $role, $audit, $users): void {
            $users->lockSecurity();
            Gate::authorize('manage-platform-catalog');
            $role = TeamAccessRole::query()->lockForUpdate()->findOrFail($role->id);
            abort_if($role->is_owner, 403, 'Owner permissions are protected.');
            $before = $role->only(['name', 'permissions', 'is_archived']);
            $role->update(['name' => $request->validated('name'), 'permissions' => $request->validated('permissions'), 'is_archived' => $request->boolean('is_archived')]);
            $audit->handle($request->user(), $role, 'role.updated', $before, $role->only(['name', 'permissions', 'is_archived']), $request->validated('reason'));
        }, 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Team role updated. Existing assignments use its current permissions.']);

        return back();
    }

    public function levels(): Response
    {
        Gate::authorize('manage-platform-catalog');

        return Inertia::render('admin/levels', ['levels' => PrivilegeLevel::query()->orderBy('id')->get()]);
    }

    public function updateLevel(UpdatePrivilegeLevelRequest $request, PrivilegeLevel $level, RecordPlatformAudit $audit, ManageUsers $users): RedirectResponse
    {
        DB::transaction(function () use ($request, $level, $audit, $users): void {
            $users->lockSecurity();
            Gate::authorize('manage-platform-catalog');
            $level = PrivilegeLevel::query()->lockForUpdate()->findOrFail($level->id);
            $before = $level->only(['name', 'team_limit']);
            $level->update($request->safe()->only(['name', 'team_limit']));
            $audit->handle($request->user(), $level, 'level.updated', $before, $level->only(['name', 'team_limit']), $request->validated('reason'));
        }, 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Privilege level updated. Existing teams are preserved.']);

        return back();
    }

    public function audit(Request $request): Response
    {
        Gate::authorize('manage-platform-catalog');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $entries = PlatformAuditEntry::query()->with('actor')->when($filters['search'] ?? null, fn ($query, $search) => $query->where('action', 'like', '%'.$search.'%'))
            ->latest('id')->paginate(20)->withQueryString()->through(fn (PlatformAuditEntry $entry): array => [
                'id' => $entry->id, 'actor' => $entry->actor->name ?? 'Server console / deleted user', 'source' => $entry->source,
                'action' => $entry->action, 'subject' => $entry->subject_type.' #'.$entry->subject_id, 'reason' => $entry->reason,
                'before' => $entry->before, 'after' => $entry->after, 'occurred_at' => $entry->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/audit', ['entries' => $entries, 'filters' => $filters]);
    }
}
