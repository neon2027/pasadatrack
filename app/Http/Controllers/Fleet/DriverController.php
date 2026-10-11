<?php

namespace App\Http\Controllers\Fleet;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\SaveDriverRequest;
use App\Models\Fleet\Driver;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,inactive']]);
        $records = Driver::forTeam($current_team)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('code')->paginate(15, ['id', 'code', 'name', 'license_number', 'phone', 'is_active'])->withQueryString();

        return Inertia::render('fleet/masters', [
            'kind' => 'drivers', 'title' => 'Drivers', 'description' => 'Keep driver details current and retain their driving history.',
            'records' => $records, 'filters' => $filters,
            'canManage' => $request->user()->hasTeamPermission($current_team, TeamPermission::ManageFleet),
        ]);
    }

    public function store(SaveDriverRequest $request, Team $current_team, RecordAuditEntry $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $current_team, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $record = Driver::query()->create([...$request->validated(), 'team_id' => $current_team->id]);
            $audit->handle($request->user(), $record, 'driver.created');
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Driver created.']);

        return to_route('drivers.index', $current_team);
    }

    public function update(SaveDriverRequest $request, Team $current_team, Driver $driver, RecordAuditEntry $audit): RedirectResponse
    {
        abort_unless($driver->team_id === $current_team->id, 404);
        DB::transaction(function () use ($request, $current_team, $driver, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $record = Driver::forTeam($current_team)->lockForUpdate()->findOrFail($driver->id);
            $before = $record->attributesToArray();
            $record->update($request->validated());
            $audit->handle($request->user(), $record, 'driver.updated', $before);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Changes saved.']);

        return back();
    }
}
