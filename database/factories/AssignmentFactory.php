<?php

namespace Database\Factories;

use App\Enums\Scheduling\AssignmentStatus;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Jeepney;
use App\Models\Fleet\Route;
use App\Models\Scheduling\Assignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Assignment> */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startsAt = now('UTC')->startOfDay()->addHours(21);

        return [
            'team_id' => TeamFactory::new(),
            'planned_driver_id' => fn (array $attributes): int => Driver::factory()->create(['team_id' => $attributes['team_id']])->id,
            'jeepney_id' => fn (array $attributes): int => Jeepney::factory()->create(['team_id' => $attributes['team_id']])->id,
            'route_id' => fn (array $attributes): int => Route::factory()->create(['team_id' => $attributes['team_id']])->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(8),
            'operational_date' => $startsAt->copy()->setTimezone('Asia/Manila')->toDateString(),
            'status' => AssignmentStatus::Planned,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => AssignmentStatus::Cancelled]);
    }
}
