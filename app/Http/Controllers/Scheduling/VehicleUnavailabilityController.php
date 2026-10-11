<?php

namespace App\Http\Controllers\Scheduling;

use App\Actions\Audit\RecordAuditEntry;
use App\Actions\Scheduling\ManageAssignments;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\SaveUnavailabilityRequest;
use App\Models\Scheduling\VehicleUnavailability;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class VehicleUnavailabilityController extends Controller
{
    public function store(SaveUnavailabilityRequest $request, Team $current_team, ManageAssignments $action): RedirectResponse
    {
        $action->blockVehicle($current_team, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jeepney marked unavailable.']);

        return back();
    }

    public function destroy(Request $request, Team $current_team, VehicleUnavailability $unavailability, RecordAuditEntry $audit): RedirectResponse
    {
        abort_unless($request->user()->hasTeamPermission($current_team, TeamPermission::ManageScheduling), 403);
        DB::transaction(function () use ($request, $current_team, $unavailability, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $before = $unavailability->attributesToArray();
            $audit->handle($request->user(), $unavailability, 'vehicle.availability-restored', $before);
            $unavailability->delete();
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jeepney availability restored.']);

        return back();
    }
}
