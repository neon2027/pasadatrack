<?php

namespace App\Http\Controllers\Fleet;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\SaveRouteRequest;
use App\Models\Fleet\Route;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RouteController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,inactive']]);
        $records = Route::forTeam($current_team)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('code')->paginate(15, ['id', 'code', 'name', 'origin', 'destination', 'default_boundary_amount', 'is_active'])->withQueryString();

        return Inertia::render('fleet/masters', [
            'kind' => 'routes', 'title' => 'Routes', 'description' => 'Set destinations and the default boundary for future driving confirmations.',
            'records' => $records, 'filters' => $filters,
            'canManage' => $request->user()->hasTeamPermission($current_team, TeamPermission::ManageFleet),
        ]);
    }

    public function store(SaveRouteRequest $request, Team $current_team, RecordAuditEntry $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $current_team, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $record = Route::query()->create([...$request->validated(), 'team_id' => $current_team->id]);
            $audit->handle($request->user(), $record, 'route.created');
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Route created.']);

        return to_route('routes.index', $current_team);
    }

    public function update(SaveRouteRequest $request, Team $current_team, Route $route, RecordAuditEntry $audit): RedirectResponse
    {
        abort_unless($route->team_id === $current_team->id, 404);
        DB::transaction(function () use ($request, $current_team, $route, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $record = Route::forTeam($current_team)->lockForUpdate()->findOrFail($route->id);
            $before = $record->attributesToArray();
            $record->update($request->validated());
            $audit->handle($request->user(), $record, 'route.updated', $before);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Changes saved.']);

        return back();
    }
}
