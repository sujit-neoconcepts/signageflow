<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Polishing;
use App\Models\Shift;
use App\Models\Polisher;
use App\Models\Moperator;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PolishingDetail>
 */
class PolishingDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'polishing_id' => Polishing::inRandomOrder()->first()?->id ?? Polishing::factory(),
            'production_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'shift_id' => Shift::inRandomOrder()->first()?->id ?? Shift::factory(),
            'polisher_id' => Polisher::inRandomOrder()->first()?->id ?? Polisher::factory(),
            'operator_id' => Moperator::inRandomOrder()->first()?->id ?? Moperator::factory(),
            'time_spent' => $this->faker->numberBetween(60, 480),
            'tubes_polished' => $this->faker->numberBetween(50, 500),
            'weight_polished' => $this->faker->randomFloat(3, 10, 1000),
            'scrap_weight' => $this->faker->randomFloat(3, 1, 50),
            'short_length_weight' => $this->faker->randomFloat(3, 1, 50),
            'tubes_repaired' => $this->faker->numberBetween(0, 20),
            'remarks' => $this->faker->optional()->sentence(),
            'pushed_to_packing' => $this->faker->boolean(70),
        ];
    }
}
