<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlittedSize>
 */
class SlittedSizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'width' => $this->faker->numberBetween(100, 2000),
            'dim' => $this->faker->numberBetween(1, 100),
        ];
    }
}