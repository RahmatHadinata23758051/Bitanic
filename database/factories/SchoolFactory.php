<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'SMK Negeri '.fake()->numberBetween(1, 99).' '.fake()->city();

        return [
            'npsn' => (string) fake()->unique()->numerify('20######'),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'province' => 'Jawa Barat',
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'settings' => [],
        ];
    }

    /**
     * Indicate that the school is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
