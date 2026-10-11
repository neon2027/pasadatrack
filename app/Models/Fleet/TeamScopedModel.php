<?php

namespace App\Models\Fleet;

use App\Models\Team;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
abstract class TeamScopedModel extends Model
{
    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Explicit scoping is not authorization; callers must supply an authorized team.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forTeam(Builder $query, Team|int $team): Builder
    {
        return $query->where($this->qualifyColumn('team_id'), $team instanceof Team ? $team->id : $team);
    }
}
