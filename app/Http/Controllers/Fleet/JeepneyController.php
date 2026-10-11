<?php

namespace App\Http\Controllers\Fleet;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\SaveJeepneyRequest;
use App\Models\Fleet\Jeepney;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JeepneyController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,inactive']]);
        $records = Jeepney::forTeam($current_team)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('plate_number', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('plate_number')->paginate(15, ['id', 'plate_number', 'name', 'capacity', 'is_active'])->withQueryString();

        return Inertia::render('fleet/masters', [
            'kind' => 'jeepneys', 'title' => 'Jeepneys', 'description' => 'Maintain your fleet and passenger capacity.',
            'records' => $records, 'filters' => $filters,
            'canManage' => $request->user()->hasTeamPermission($current_team, TeamPermission::ManageOperations),
        ]);
    }

    public function store(SaveJeepneyRequest $request, Team $current_team, RecordAuditEntry $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $current_team, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $record = Jeepney::query()->create([...$request->validated(), 'team_id' => $current_team->id]);
            $audit->handle($request->user(), $record, 'jeepney.created');
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jeepney created.']);

        return to_route('jeepneys.index', $current_team);
    }

    public function update(SaveJeepneyRequest $request, Team $current_team, Jeepney $jeepney, RecordAuditEntry $audit): RedirectResponse
    {
        abort_unless($jeepney->team_id === $current_team->id, 404);
        DB::transaction(function () use ($request, $current_team, $jeepney, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $record = Jeepney::forTeam($current_team)->lockForUpdate()->findOrFail($jeepney->id);
            $before = $record->attributesToArray();
            $record->update($request->validated());
            $audit->handle($request->user(), $record, 'jeepney.updated', $before);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Changes saved.']);

        return back();
    }
}
