<?php

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdministrator() && ($user->platform_role === PlatformRole::SuperAdmin || $target->platform_role === PlatformRole::User);
    }
}
