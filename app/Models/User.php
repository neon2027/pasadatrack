<?php

namespace App\Models;

use App\Concerns\HasTeams;
use App\Enums\AccountStatus;
use App\Enums\PlatformRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property PlatformRole $platform_role
 * @property AccountStatus $account_status
 * @property int $privilege_level_id
 * @property int $auth_version
 * @property-read PrivilegeLevel $privilegeLevel
 * @property string $name
 * @property string $email
 * @property string|null $google_id
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $onboarding_dismissed_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 */
#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['google_id', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @var array<string, mixed> */
    protected $attributes = ['platform_role' => 'user', 'account_status' => 'active', 'privilege_level_id' => 1, 'auth_version' => 0];

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarding_dismissed_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'platform_role' => PlatformRole::class,
            'account_status' => AccountStatus::class,
            'privilege_level_id' => 'integer',
            'auth_version' => 'integer',
        ];
    }

    /** @return BelongsTo<PrivilegeLevel, $this> */
    public function privilegeLevel(): BelongsTo
    {
        return $this->belongsTo(PrivilegeLevel::class);
    }

    public function isActive(): bool
    {
        return $this->account_status === AccountStatus::Active;
    }

    public function isAdministrator(): bool
    {
        return $this->isActive() && $this->platform_role->isAdministrator();
    }

    /** @return array{level: string, owned: int, limit: int, canCreate: bool} */
    public function teamQuota(): array
    {
        $level = $this->privilegeLevel;
        $owned = $this->ownedTeams()->count();

        return ['level' => $level->name, 'owned' => $owned, 'limit' => $level->team_limit, 'canCreate' => $this->isActive() && $owned < $level->team_limit];
    }
}
