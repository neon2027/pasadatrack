<?php

namespace App\Actions\Scheduling;

use App\Actions\Audit\RecordAuditEntry;
use App\Data\Boundary\Money;
use App\Data\OperationsTime;
use App\Enums\Scheduling\AssignmentStatus;
use App\Models\Boundary\BoundaryObligation;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Jeepney;
use App\Models\Fleet\Route;
use App\Models\Scheduling\Assignment;
use App\Models\Scheduling\DrivingRecord;
use App\Models\Scheduling\VehicleUnavailability;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageAssignments
{
    public function __construct(private RecordAuditEntry $audit) {}

    /** @param array<string, mixed> $data */
    public function save(Team $team, User $actor, array $data, ?Assignment $assignment = null): Assignment
    {
        return DB::transaction(function () use ($team, $actor, $data, $assignment): Assignment {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $assignment = $assignment ? Assignment::forTeam($team)->lockForUpdate()->findOrFail($assignment->id) : new Assignment;
            if ($assignment->exists && $assignment->status !== AssignmentStatus::Planned) {
                throw ValidationException::withMessages(['status' => 'Only planned shifts can be edited.']);
            }

            $driver = Driver::forTeam($team)->where('is_active', true)->findOrFail((int) $data['planned_driver_id']);
            $jeepney = Jeepney::forTeam($team)->where('is_active', true)->findOrFail((int) $data['jeepney_id']);
            Route::forTeam($team)->where('is_active', true)->findOrFail((int) $data['route_id']);
            $start = OperationsTime::utc($data['starts_at']);
            $end = OperationsTime::utc($data['ends_at']);
            $this->ensureAvailable($team, $jeepney->id, $driver->id, $start, $end, $assignment->id);
            $before = $assignment->exists ? $assignment->attributesToArray() : null;
            $assignment->fill([
                'team_id' => $team->id, 'planned_driver_id' => $driver->id, 'jeepney_id' => $jeepney->id,
                'route_id' => $data['route_id'], 'starts_at' => $start, 'ends_at' => $end,
                'operational_date' => $start->setTimezone(OperationsTime::TIMEZONE)->toDateString(),
                'status' => AssignmentStatus::Planned,
            ])->save();
            $this->audit->handle($actor, $assignment, $before ? 'assignment.updated' : 'assignment.created', $before);

            return $assignment;
        }, 5);
    }

    public function confirm(Team $team, User $actor, Assignment $assignment, int $driverId, ?string $expectedBoundary = null): void
    {
        DB::transaction(function () use ($team, $actor, $assignment, $driverId, $expectedBoundary): void {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $assignment = Assignment::forTeam($team)->lockForUpdate()->findOrFail($assignment->id);
            if (! $assignment->status->canTransitionTo(AssignmentStatus::Confirmed)) {
                throw ValidationException::withMessages(['status' => 'This shift has already been confirmed or cancelled.']);
            }
            $driver = Driver::forTeam($team)->where('is_active', true)->findOrFail($driverId);
            Jeepney::forTeam($team)->where('is_active', true)->findOrFail($assignment->jeepney_id);
            $route = Route::forTeam($team)->where('is_active', true)->findOrFail($assignment->route_id);
            if ($expectedBoundary !== null && Money::cents($expectedBoundary) !== Money::cents($route->default_boundary_amount)) {
                throw ValidationException::withMessages(['expected_boundary_amount' => 'The route boundary changed. Refresh the page and review the new amount before confirming.']);
            }
            $this->ensureAvailable($team, $assignment->jeepney_id, $driver->id, $assignment->starts_at, $assignment->ends_at, $assignment->id);
            $before = $assignment->attributesToArray();
            $assignment->update(['status' => AssignmentStatus::Confirmed]);
            $record = DrivingRecord::query()->create([
                'team_id' => $team->id, 'assignment_id' => $assignment->id, 'actual_driver_id' => $driver->id,
                'confirmed_by' => $actor->id, 'confirmed_at' => now('UTC'),
                'boundary_amount' => $route->default_boundary_amount, 'driving_date' => $assignment->operational_date,
            ]);
            BoundaryObligation::query()->create([
                'team_id' => $team->id, 'driving_record_id' => $record->id,
                'driver_id' => $driver->id, 'original_amount' => $record->boundary_amount,
            ]);
            $this->audit->handle($actor, $assignment, 'assignment.confirmed', $before);
            $this->audit->handle($actor, $record, 'driving.confirmed');
        }, 5);
    }

    public function cancel(Team $team, User $actor, Assignment $assignment, string $reason): void
    {
        DB::transaction(function () use ($team, $actor, $assignment, $reason): void {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $assignment = Assignment::forTeam($team)->lockForUpdate()->findOrFail($assignment->id);
            if (! $assignment->status->canTransitionTo(AssignmentStatus::Cancelled)) {
                throw ValidationException::withMessages(['status' => 'Only planned shifts can be cancelled.']);
            }
            $before = $assignment->attributesToArray();
            $assignment->update(['status' => AssignmentStatus::Cancelled]);
            $this->audit->handle($actor, $assignment, 'assignment.cancelled', $before, $reason);
        }, 5);
    }

    /** @param array<string, mixed> $data */
    public function blockVehicle(Team $team, User $actor, array $data): void
    {
        DB::transaction(function () use ($team, $actor, $data): void {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $start = OperationsTime::utc($data['starts_at']);
            $end = OperationsTime::utc($data['ends_at']);
            if (Assignment::forTeam($team)->where('jeepney_id', $data['jeepney_id'])
                ->where('status', '!=', AssignmentStatus::Cancelled)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                throw ValidationException::withMessages(['jeepney_id' => 'Cancel or reschedule overlapping shifts before marking this jeepney unavailable.']);
            }
            $block = VehicleUnavailability::query()->create([
                'team_id' => $team->id, 'jeepney_id' => $data['jeepney_id'], 'starts_at' => $start, 'ends_at' => $end, 'reason' => $data['reason'],
            ]);
            $this->audit->handle($actor, $block, 'vehicle.unavailable', reason: $data['reason']);
        }, 5);
    }

    private function ensureAvailable(Team $team, int $jeepneyId, int $driverId, CarbonImmutable $start, CarbonImmutable $end, ?int $except): void
    {
        $overlap = Assignment::forTeam($team)->where('status', '!=', AssignmentStatus::Cancelled)
            ->when($except, fn ($query) => $query->whereKeyNot($except))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start);
        if ((clone $overlap)->where('jeepney_id', $jeepneyId)->exists()) {
            throw ValidationException::withMessages(['jeepney_id' => 'This jeepney already has an overlapping shift.']);
        }
        if ((clone $overlap)->where(function ($query) use ($driverId): void {
            $query->where(fn ($planned) => $planned->where('status', AssignmentStatus::Planned)->where('planned_driver_id', $driverId))
                ->orWhereHas('drivingRecord', fn ($record) => $record->where('actual_driver_id', $driverId));
        })->exists()) {
            throw ValidationException::withMessages(['planned_driver_id' => 'This driver already has an overlapping shift.', 'actual_driver_id' => 'This driver already has an overlapping shift.']);
        }
        if (VehicleUnavailability::forTeam($team)->where('jeepney_id', $jeepneyId)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
            throw ValidationException::withMessages(['jeepney_id' => 'This jeepney is unavailable during the selected period.']);
        }
    }
}
