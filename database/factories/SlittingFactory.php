<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Coil;
use App\Models\Shift;
use App\Models\Slitter;
use App\Models\Moperator;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Slitting>
 */
class SlittingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coilid' => Coil::inRandomOrder()->first()?->id ?? Coil::factory(),
            'waste_mm' => $this->faker->numberBetween(10, 100),
            'waste_cal' => $this->faker->randomFloat(3, 50, 500),
            'waste_ac' => $this->faker->randomFloat(3, 50, 500),
            'scrap' => $this->faker->randomFloat(3, 10, 200),
            'sdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'fdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'shift_id' => Shift::inRandomOrder()->first()?->id ?? Shift::factory(),
            'slitter_id' => Slitter::inRandomOrder()->first()?->id ?? Slitter::factory(),
            'moperator_id' => Moperator::inRandomOrder()->first()?->id ?? Moperator::factory(),
            'preperedby' => $this->faker->name(),
            'slit_status' => $this->faker->randomElement([0, 1, 2, 3, 4]),
        ];
    }
}
