<?php

namespace Database\Factories;

use App\Models\Fleet\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Route> */
class RouteFactory extends Factory
{
    protected $model = Route::class;

    /** @return array{team_id: TeamFactory, code: string, name: string, origin: string, destination: string, default_boundary_amount: string, is_active: bool} */
    public function definition(): array
    {
        return [
            'team_id' => TeamFactory::new(),
            'code' => fake()->unique()->bothify('RTE-####'),
            'name' => 'Cubao - Quiapo',
            'origin' => 'Cubao',
            'destination' => 'Quiapo',
            'default_boundary_amount' => '1200.00',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
