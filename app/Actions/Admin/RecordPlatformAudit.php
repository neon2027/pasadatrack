<?php

namespace App\Actions\Admin;

use App\Models\PlatformAuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordPlatformAudit
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function handle(?User $actor, Model $subject, string $action, ?array $before, ?array $after, string $reason, string $source = 'web'): void
    {
        PlatformAuditEntry::query()->create(['actor_id' => $actor?->id, 'source' => $source, 'action' => $action,
            'subject_type' => $subject->getTable(), 'subject_id' => $subject->getKey(), 'before' => $before, 'after' => $after, 'reason' => $reason]);
    }
}
