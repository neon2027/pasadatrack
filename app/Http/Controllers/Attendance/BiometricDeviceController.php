<?php

namespace App\Http\Controllers\Attendance;

use App\Actions\Attendance\ImportBiometricEvents;
use App\Actions\Audit\RecordAuditEntry;
use App\Data\OperationsTime;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\EnrollDeviceUserRequest;
use App\Http\Requests\Attendance\ImportBiometricEventsRequest;
use App\Http\Requests\Attendance\RevokeEnrollmentRequest;
use App\Http\Requests\Attendance\SaveDeviceRequest;
use App\Models\Attendance\BiometricDevice;
use App\Models\Attendance\BiometricDeviceUser;
use App\Models\Attendance\BiometricEvent;
use App\Models\Fleet\Driver;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BiometricDeviceController extends Controller
{
    public function revoke(RevokeEnrollmentRequest $request, Team $current_team, BiometricDevice $device, BiometricDeviceUser $enrollment, RecordAuditEntry $audit): RedirectResponse
    {
        abort_unless($enrollment->biometric_device_id === $device->id, 404);
        DB::transaction(function () use ($request, $current_team, $enrollment, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $enrollment = BiometricDeviceUser::forTeam($current_team)->lockForUpdate()->findOrFail($enrollment->id);
            if ($enrollment->revoked_at !== null) {
                throw ValidationException::withMessages(['reason' => 'This enrollment has already been revoked.']);
            }
            $before = $enrollment->attributesToArray();
            $enrollment->update(['revoked_at' => now('UTC')]);
            $audit->handle($request->user(), $enrollment, 'device.enrollment-revoked', $before, $request->validated('reason'));
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enrollment revoked. Historical attendance stays intact; later scans remain pending.']);

        return back();
    }

    public function index(Request $request, Team $current_team): Response
    {
        return Inertia::render('attendance/devices', [
            'devices' => BiometricDevice::forTeam($current_team)->with('deviceUsers.driver')->orderBy('code')->paginate(10)->withQueryString(),
            'deviceOptions' => BiometricDevice::forTeam($current_team)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'drivers' => Driver::forTeam($current_team)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'events' => BiometricEvent::forTeam($current_team)->with('biometricDevice')->withExists(['inSessions', 'outSessions'])->latest('occurred_at')->orderByDesc('id')->paginate(15, ['*'], 'events_page')->withQueryString()
                ->through(fn ($event): array => [
                    'id' => $event->id, 'device' => $event->biometricDevice->name, 'event_key' => $event->event_key,
                    'external_user_id' => $event->external_user_id, 'occurred_at' => OperationsTime::local($event->occurred_at),
                    'direction' => $event->raw_metadata['direction'] ?? 'unknown', 'processed' => $event->in_sessions_exists || $event->out_sessions_exists,
                ]),
        ]);
    }

    public function store(SaveDeviceRequest $request, Team $current_team, RecordAuditEntry $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $current_team, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $device = BiometricDevice::query()->create([...$request->validated(), 'team_id' => $current_team->id]);
            $audit->handle($request->user(), $device, 'device.created');
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attendance device registered.']);

        return back();
    }

    public function update(SaveDeviceRequest $request, Team $current_team, BiometricDevice $device, RecordAuditEntry $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $current_team, $device, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $before = $device->attributesToArray();
            $device->update($request->validated());
            $audit->handle($request->user(), $device, 'device.updated', $before);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Changes saved.']);

        return back();
    }

    public function enroll(EnrollDeviceUserRequest $request, Team $current_team, BiometricDevice $device, RecordAuditEntry $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $current_team, $device, $audit): void {
            Team::query()->whereKey($current_team->id)->lockForUpdate()->firstOrFail();
            $mapping = BiometricDeviceUser::query()->create([...$request->validated(), 'team_id' => $current_team->id, 'biometric_device_id' => $device->id]);
            $audit->handle($request->user(), $mapping, 'device.driver-enrolled');
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Device user matched to driver. Process pending scans to retry unmatched events.']);

        return back();
    }

    public function import(ImportBiometricEventsRequest $request, Team $current_team, BiometricDevice $device, ImportBiometricEvents $action): RedirectResponse
    {
        $action->handle($current_team, $request->user(), $device, $request->validated('events'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Scans imported. Review pending events for unmatched users or conflicting times.']);

        return back();
    }

    public function process(Request $request, Team $current_team, BiometricDevice $device, ImportBiometricEvents $action): RedirectResponse
    {
        abort_unless($request->user()->hasTeamPermission($current_team, TeamPermission::ManageDevices), 403);
        $action->processPending($current_team, $request->user(), $device);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pending scans processed.']);

        return back();
    }
}
