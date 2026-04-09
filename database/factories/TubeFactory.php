<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Strip;
use App\Models\Shift;
use App\Models\Tubemill;
use App\Models\Moperator;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TubeFactory>
 */
class TubeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tpdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'tfdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'shift_id' => Shift::inRandomOrder()->first()?->id ?? Shift::factory(),
            'mill_id' => Tubemill::inRandomOrder()->first()?->id ?? Tubemill::factory(),
            'moperator_id' => Moperator::inRandomOrder()->first()?->id ?? Moperator::factory(),
            'strip_id' => Strip::inRandomOrder()->first()?->id ?? Strip::factory(),
            'strip_width' => $this->faker->numberBetween(50, 500),
            'strip_batch' => $this->faker->regexify('[A-Z]{2}[0-9]{6}'),
            'tube_type' => $this->faker->randomElement(['Jindal', 'Non Jindal']),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'tubesize' => $this->faker->numberBetween(10, 200),
            'tubelength' => $this->faker->numberBetween(3000, 6000),
            'planned_psc' => $this->faker->numberBetween(100, 1000),
            'wt_per_tube_cal' => $this->faker->randomFloat(3, 1, 10),
            'wst_cal' => $this->faker->numberBetween(10, 100),
            'preperedby' => $this->faker->name(),
            'tube_status' => $this->faker->randomElement([0, 1, 2]),
            'pushed_to_polishing' => $this->faker->boolean(30),
        ];
    }
}