<?php

namespace App\Enums;

enum PlatformRole: string
{
    case User = 'user';
    case Admin = 'admin';
    case SuperAdmin = 'super-admin';

    public function isAdministrator(): bool
    {
        return $this !== self::User;
    }
}
