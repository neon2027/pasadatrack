<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property TeamRole $role
 * @property int|null $team_access_role_id
 * @property-read TeamAccessRole|null $accessRole
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 * @property-read User $user
 */
#[Fillable(['team_id', 'user_id', 'role', 'team_access_role_id'])]
class Membership extends Pivot
{
    protected static function booted(): void
    {
        static::creating(function (self $membership): void {
            $membership->team_access_role_id ??= TeamAccessRole::query()->where('key', $membership->role->value)->value('id');
        });
    }

    /** @return BelongsTo<TeamAccessRole, $this> */
    public function accessRole(): BelongsTo
    {
        return $this->belongsTo(TeamAccessRole::class, 'team_access_role_id');
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'team_members';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Get the team that the membership belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that belongs to this membership.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
        ];
    }
}
