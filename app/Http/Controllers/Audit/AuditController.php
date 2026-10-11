<?php

namespace App\Http\Controllers\Audit;

use App\Data\OperationsTime;
use App\Http\Controllers\Controller;
use App\Models\Audit\AuditEntry;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return Inertia::render('audit/index', [
            'entries' => AuditEntry::forTeam($current_team)->with('actor')
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('action', 'like', '%'.$search.'%'))
                ->latest('id')->paginate(20)->withQueryString()->through(fn ($entry): array => [
                    'id' => $entry->id, 'action' => $entry->action, 'subject' => $entry->subject_type.' #'.$entry->subject_id,
                    'actor' => $entry->actor->name ?? 'System', 'occurred_at' => OperationsTime::local($entry->occurred_at),
                    'reason' => $entry->reason, 'before' => $entry->before, 'after' => $entry->after,
                ]),
            'filters' => $filters,
        ]);
    }
}
