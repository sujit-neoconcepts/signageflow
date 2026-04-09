<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Tube;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Polishing>
 */
class PolishingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tube_id' => Tube::inRandomOrder()->first()?->id ?? Tube::factory(),
            'ppdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'pfdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'preperedby' => $this->faker->name(),
            'total_tubes_given' => $this->faker->numberBetween(1, 100),
            'total_weight_given' => $this->faker->randomFloat(3, 10, 1000),
            'total_tubes_polished' => $this->faker->numberBetween(1, 100),
            'total_weight_polished' => $this->faker->randomFloat(3, 10, 1000),
            'short_length_weight' => $this->faker->randomFloat(3, 10, 1000),
            'scrap_weight' => $this->faker->randomFloat(3, 10, 1000),
            'polishing_status' => $this->faker->randomElement([0, 1, 2]),
        ];
    }
}
