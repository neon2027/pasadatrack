<?php

namespace App\Actions\Audit;

use App\Models\Audit\AuditEntry;
use App\Models\Fleet\TeamScopedModel;
use App\Models\User;

class RecordAuditEntry
{
    /** @param array<string, mixed>|null $before */
    public function handle(User $actor, TeamScopedModel $subject, string $action, ?array $before = null, ?string $reason = null): void
    {
        AuditEntry::query()->create([
            'team_id' => $subject->team_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject->getTable(),
            'subject_id' => $subject->id,
            'before' => $before,
            'after' => $subject->attributesToArray(),
            'reason' => $reason,
            'occurred_at' => now('UTC'),
        ]);
    }
}
