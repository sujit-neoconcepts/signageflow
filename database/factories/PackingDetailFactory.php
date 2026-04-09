<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Packing;
use App\Models\Shift;
use App\Models\Moperator;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PackingDetail>
 */
class PackingDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'packing_id' => Packing::inRandomOrder()->first()?->id ?? Packing::factory(),
            'production_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'shift_id' => Shift::inRandomOrder()->first()?->id ?? Shift::factory(),
            'operator_id' => Moperator::inRandomOrder()->first()?->id ?? Moperator::factory(),
            'time_spent' => $this->faker->numberBetween(60, 480),
            'tubes_packed' => $this->faker->numberBetween(50, 500),
            'weight_packed' => $this->faker->randomFloat(3, 100, 1000),
            'remarks' => $this->faker->optional()->sentence(),
        ];
    }
}
