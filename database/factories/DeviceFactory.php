<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::where('slug', 'smk-ppn-1-sukaraja')->value('id') ?? School::first()?->id,
            'code' => 'TK-'.strtoupper(fake()->unique()->bothify('##??')),
            'name' => 'Meja Praktikum '.fake()->word(),
            'connection_type' => 'wifi',
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the device is unassigned to any school.
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'school_id' => null,
        ]);
    }
}
