<?php

namespace Database\Factories;

use App\Models\Fleet\Jeepney;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Jeepney> */
class JeepneyFactory extends Factory
{
    protected $model = Jeepney::class;

    /** @return array{team_id: TeamFactory, plate_number: string, name: string, capacity: int, is_active: bool} */
    public function definition(): array
    {
        return [
            'team_id' => TeamFactory::new(),
            'plate_number' => strtoupper(fake()->unique()->bothify('???-####')),
            'name' => fake()->bothify('Unit ##'),
            'capacity' => 20,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
