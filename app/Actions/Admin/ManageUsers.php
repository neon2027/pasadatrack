<?php

namespace App\Actions\Admin;

use App\Actions\Teams\CreateTeam;
use App\Enums\AccountStatus;
use App\Enums\PlatformRole;
use App\Models\GoogleLinkChallenge;
use App\Models\PrivilegeLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageUsers
{
    public function __construct(private CreateTeam $createTeam, private RecordPlatformAudit $audit) {}

    public function lockSecurity(): void
    {
        DB::table('platform_security_locks')->where('id', 1)->increment('version');
    }

    /** @return array<string, mixed> */
    public function snapshot(User $user): array
    {
        return $user->only(['name', 'email', 'platform_role', 'account_status', 'privilege_level_id']);
    }

    private function authorizeActor(?User $actor, ?User $target, PlatformRole $newRole, string $source): void
    {
        if ($source === 'console' && $actor === null) {
            return;
        }
        $actor = $actor ? User::query()->findOrFail($actor->id) : null;
        abort_unless($actor?->isAdministrator(), 403);
        if ($actor->platform_role !== PlatformRole::SuperAdmin) {
            abort_unless($newRole === PlatformRole::User && (! $target || $target->platform_role === PlatformRole::User), 403);
        }
    }

    /** @param array{name: string, email: string, privilege_level_id: int|string, platform_role?: string, reason: string} $data */
    public function provision(?User $actor, array $data, string $source = 'web'): User
    {
        return DB::transaction(function () use ($actor, $data, $source): User {
            $this->lockSecurity();
            $role = PlatformRole::from($data['platform_role'] ?? 'user');
            $this->authorizeActor($actor, null, $role, $source);
            PrivilegeLevel::query()->findOrFail($data['privilege_level_id']);
            $email = Str::lower(trim($data['email']));
            if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email' => __('An account already exists for this email address.')]);
            }
            $user = User::query()->create(['name' => $data['name'], 'email' => $email, 'password' => Str::random(64)]);
            $user->forceFill(['platform_role' => $role, 'account_status' => AccountStatus::Active, 'privilege_level_id' => $data['privilege_level_id']])->save();
            $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);
            $user->refresh();
            $this->audit->handle($actor, $user, 'user.provisioned', null, $this->snapshot($user), $data['reason'], $source);

            return $user;
        }, 3);
    }

    /** @param array{name?: string, account_status?: string, privilege_level_id?: int|string, platform_role?: string, reason: string} $data */
    public function update(?User $actor, User $target, array $data, string $source = 'web'): User
    {
        return DB::transaction(function () use ($actor, $target, $data, $source): User {
            $this->lockSecurity();
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            $role = PlatformRole::from($data['platform_role'] ?? $target->platform_role->value);
            $status = AccountStatus::from($data['account_status'] ?? $target->account_status->value);
            $this->authorizeActor($actor, $target, $role, $source);
            if ($actor?->id === $target->id && $status === AccountStatus::Suspended) {
                throw ValidationException::withMessages(['account_status' => __('You cannot suspend your own account.')]);
            }
            $this->ensureSuperAdminRemains($target, $role, $status);
            $before = $this->snapshot($target);
            $revoke = $target->platform_role !== $role || $target->account_status !== $status;
            $target->forceFill([
                'name' => $data['name'] ?? $target->name,
                'platform_role' => $role, 'account_status' => $status,
                'privilege_level_id' => $data['privilege_level_id'] ?? $target->privilege_level_id,
                'auth_version' => $target->auth_version + ($revoke ? 1 : 0),
                'remember_token' => $revoke ? null : $target->remember_token,
            ])->save();
            if ($revoke) {
                DB::table('sessions')->where('user_id', $target->id)->delete();
                GoogleLinkChallenge::query()->where('user_id', $target->id)->delete();
            }
            $this->audit->handle($actor, $target, 'user.updated', $before, $this->snapshot($target), $data['reason'], $source);

            return $target;
        }, 3);
    }

    private function ensureSuperAdminRemains(User $target, PlatformRole $role, AccountStatus $status): void
    {
        if ($target->platform_role === PlatformRole::SuperAdmin && $target->isActive()
            && ($role !== PlatformRole::SuperAdmin || $status !== AccountStatus::Active)
            && ! User::query()->where('platform_role', PlatformRole::SuperAdmin)->where('account_status', AccountStatus::Active)->whereKeyNot($target->id)->exists()) {
            throw ValidationException::withMessages(['platform_role' => __('At least one active Super Admin must remain.')]);
        }
    }

    public function deleteSelf(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->lockSecurity();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->ensureSuperAdminRemains($user, PlatformRole::User, AccountStatus::Suspended);
            $this->audit->handle($user, $user, 'user.deleted', $this->snapshot($user), null, 'Account owner deleted their profile.');
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        }, 3);
    }
}
