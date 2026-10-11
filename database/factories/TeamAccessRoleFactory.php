<?php

namespace Database\Factories;

use App\Models\TeamAccessRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TeamAccessRole> */
class TeamAccessRoleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['key' => 'custom-'.Str::uuid(), 'name' => fake()->unique()->jobTitle(), 'permissions' => [], 'is_owner' => false, 'is_archived' => false];
    }
}
