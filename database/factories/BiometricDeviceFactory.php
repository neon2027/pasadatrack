<?php

namespace Database\Factories;

use App\Models\Attendance\BiometricDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BiometricDevice> */
class BiometricDeviceFactory extends Factory
{
    protected $model = BiometricDevice::class;

    /** @return array{team_id: TeamFactory, code: string, name: string, metadata: array{location: string}, is_active: bool} */
    public function definition(): array
    {
        return [
            'team_id' => TeamFactory::new(),
            'code' => fake()->unique()->bothify('BIO-####'),
            'name' => 'Terminal attendance device',
            'metadata' => ['location' => 'Cubao terminal'],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
