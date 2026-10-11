<?php

namespace App\Models\Audit;

use App\Models\Boundary\AppendOnlyModel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * subject_type/id are historical identifiers, deliberately not cascading FKs.
 * Services validate a stable subject alias and its tenant; null actors are system actions.
 *
 * @property int|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property int $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $reason
 * @property CarbonImmutable $occurred_at
 * @property-read User|null $actor
 */
#[Fillable(['team_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'reason', 'occurred_at'])]
class AuditEntry extends AppendOnlyModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
