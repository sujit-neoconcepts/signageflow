<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Tube;
use App\Models\Shift;
use App\Models\Moperator;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TubingFactory>
 */
class TubingFactory extends Factory
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
            'production_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'shift_id' => Shift::inRandomOrder()->first()?->id ?? Shift::factory(),
            'tubemill_id' => $this->faker->numberBetween(1, 10),
            'operator_id' => Moperator::inRandomOrder()->first()?->id ?? Moperator::factory(),
            'time_spent' => $this->faker->numberBetween(60, 480),
            'tubes_made' => $this->faker->numberBetween(50, 500),
            'weight_produced' => $this->faker->randomFloat(3, 100, 1000),
            'scrap_weight' => $this->faker->randomFloat(3, 5, 50),
            'short_length_weight' => $this->faker->randomFloat(3, 5, 50),
            'tubes_repaired' => $this->faker->numberBetween(0, 20),
            'remarks' => $this->faker->optional()->sentence(),
        ];
    }
}