<?php

namespace Database\Factories;

use App\Models\Fleet\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Driver> */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    /** @return array{team_id: TeamFactory, code: string, name: string, license_number: string, phone: string, is_active: bool} */
    public function definition(): array
    {
        return [
            'team_id' => TeamFactory::new(),
            'code' => fake()->unique()->bothify('DRV-######'),
            'name' => fake()->name('male'),
            'license_number' => fake()->bothify('N##-##-######'),
            'phone' => fake()->numerify('+639#########'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
