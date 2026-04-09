<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Moperator>
 */
class MoperatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mname' => $this->faker->name(),
            'dep_id' => $this->faker->randomElement([1, 2, 3, 4]), // Department IDs
        ];
    }
}
