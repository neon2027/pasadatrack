<?php

namespace Database\Factories;

use App\Models\PrivilegeLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PrivilegeLevel> */
class PrivilegeLevelFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'team_limit' => fake()->numberBetween(1, 10)];
    }
}
