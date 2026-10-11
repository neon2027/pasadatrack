<?php

namespace App\Http\Controllers\Scheduling;

use App\Actions\Scheduling\ManageAssignments;
use App\Data\OperationsTime;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\ConfirmDrivingRequest;
use App\Http\Requests\Scheduling\SaveAssignmentRequest;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Jeepney;
use App\Models\Fleet\Route;
use App\Models\Scheduling\Assignment;
use App\Models\Scheduling\VehicleUnavailability;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'status' => ['nullable', 'in:planned,confirmed,cancelled']]);

        return Inertia::render('scheduling/index', [
            'assignments' => Assignment::forTeam($current_team)->with(['plannedDriver', 'jeepney', 'route', 'drivingRecord.actualDriver'])
                ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('operational_date', $date))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest('starts_at')->orderByDesc('id')->paginate(15)->withQueryString()->through(fn (Assignment $assignment): array => [
                    'id' => $assignment->id, 'planned_driver_id' => $assignment->planned_driver_id,
                    'jeepney_id' => $assignment->jeepney_id, 'route_id' => $assignment->route_id,
                    'starts_at' => OperationsTime::local($assignment->starts_at), 'ends_at' => OperationsTime::local($assignment->ends_at),
                    'date' => $assignment->operational_date->toDateString(), 'status' => $assignment->status->value,
                    'driver' => $assignment->plannedDriver->name, 'plate' => $assignment->jeepney->plate_number,
                    'route' => $assignment->route->name, 'actual_driver' => $assignment->drivingRecord?->actualDriver->name,
                    'boundary' => $assignment->drivingRecord?->boundary_amount,
                    'route_boundary' => $assignment->route->default_boundary_amount,
                ]),
            'unavailabilities' => VehicleUnavailability::forTeam($current_team)->with('jeepney')->latest('starts_at')->orderByDesc('id')->paginate(10, ['*'], 'unavailable_page')->withQueryString()
                ->through(fn ($block): array => ['id' => $block->id, 'plate' => $block->jeepney->plate_number, 'starts_at' => OperationsTime::local($block->starts_at), 'ends_at' => OperationsTime::local($block->ends_at), 'reason' => $block->reason]),
            'drivers' => Driver::forTeam($current_team)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'jeepneys' => Jeepney::forTeam($current_team)->where('is_active', true)->orderBy('plate_number')->get(['id', 'plate_number']),
            'routes' => Route::forTeam($current_team)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $filters, 'canManage' => $request->user()->hasTeamPermission($current_team, TeamPermission::ManageOperations),
        ]);
    }

    public function store(SaveAssignmentRequest $request, Team $current_team, ManageAssignments $action): RedirectResponse
    {
        $action->save($current_team, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Shift scheduled.']);

        return to_route('assignments.index', $current_team);
    }

    public function update(SaveAssignmentRequest $request, Team $current_team, Assignment $assignment, ManageAssignments $action): RedirectResponse
    {
        $action->save($current_team, $request->user(), $request->validated(), $assignment);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Changes saved.']);

        return back();
    }

    public function confirm(ConfirmDrivingRequest $request, Team $current_team, Assignment $assignment, ManageAssignments $action): RedirectResponse
    {
        $action->confirm($current_team, $request->user(), $assignment, (int) $request->validated('actual_driver_id'), (string) $request->validated('expected_boundary_amount'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Driving confirmed and boundary recorded.']);

        return back();
    }

    public function cancel(Request $request, Team $current_team, Assignment $assignment, ManageAssignments $action): RedirectResponse
    {
        abort_unless($request->user()->hasTeamPermission($current_team, TeamPermission::ManageOperations), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $action->cancel($current_team, $request->user(), $assignment, $data['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Shift cancelled.']);

        return back();
    }
}
