<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Thickness>
 */
class ThicknessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseThicknesses = [0.5, 0.6, 0.7, 0.8, 0.9, 1.0, 1.2, 1.5, 2.0, 2.5, 3.0, 4.0, 5.0, 6.0];
        $variation = $this->faker->randomFloat(2, -0.05, 0.05); // Small variation

        return [
            'tvalue' => round($this->faker->randomElement($baseThicknesses) + $variation, 2),
        ];
    }
}
