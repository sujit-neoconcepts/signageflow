<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TubeLength>
 */
class TubeLengthFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lfeet' => $this->faker->numberBetween(1, 100),
            'mm' => $this->faker->numberBetween(1, 100),
        ];
    }
}