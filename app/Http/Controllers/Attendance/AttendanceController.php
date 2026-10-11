<?php

namespace App\Http\Controllers\Attendance;

use App\Actions\Attendance\ManageAttendance;
use App\Data\OperationsTime;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\SaveAttendanceRequest;
use App\Models\Attendance\AttendanceSession;
use App\Models\Fleet\Driver;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'driver_id' => ['nullable', 'integer']]);

        return Inertia::render('attendance/index', [
            'canManageDevices' => $request->user()->hasTeamPermission($current_team, TeamPermission::ManageDevices),
            'sessions' => AttendanceSession::forTeam($current_team)->with(['driver', 'corrections.correctedBy'])
                ->when($filters['driver_id'] ?? null, fn ($query, $id) => $query->where('driver_id', $id))
                ->when($filters['date'] ?? null, fn ($query, $date) => $query->where('in_at', '>=', OperationsTime::utc($date.'T00:00'))->where('in_at', '<', OperationsTime::utc($date.'T00:00')->addDay()))
                ->latest('in_at')->orderByDesc('id')->paginate(15)->withQueryString()->through(fn ($session): array => [
                    'id' => $session->id, 'driver_id' => $session->driver_id, 'driver' => $session->driver->name,
                    'source' => $session->source->value, 'in_at' => OperationsTime::local($session->in_at), 'out_at' => OperationsTime::local($session->out_at),
                    'corrections' => $session->corrections->map(fn ($correction): array => ['reason' => $correction->reason, 'actor' => $correction->correctedBy->name])->all(),
                ]),
            'drivers' => Driver::forTeam($current_team)->orderBy('name')->get(['id', 'name']),
            'filters' => $filters, 'canManage' => $request->user()->hasTeamPermission($current_team, TeamPermission::ManageAttendance),
        ]);
    }

    public function store(SaveAttendanceRequest $request, Team $current_team, ManageAttendance $action): RedirectResponse
    {
        $action->save($current_team, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Manual attendance recorded.']);

        return back();
    }

    public function update(SaveAttendanceRequest $request, Team $current_team, AttendanceSession $attendance, ManageAttendance $action): RedirectResponse
    {
        $action->save($current_team, $request->user(), $request->validated(), $attendance);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attendance correction recorded.']);

        return back();
    }
}
