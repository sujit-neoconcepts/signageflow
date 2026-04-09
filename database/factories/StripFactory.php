<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Slitting;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StripFactory>
 */
class StripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slitid' => Slitting::inRandomOrder()->first()?->id ?? Slitting::factory(),
            'strip_width' => $this->faker->numberBetween(50, 500),
            'noofpsc' => $this->faker->numberBetween(1, 100),
            'strip_status' => $this->faker->randomElement([0, 1, 2, 3, 4, 5]),
            'strip_wt' => $this->faker->randomFloat(3, 50, 500),
            'strip_wt_ac' => $this->faker->randomFloat(3, 50, 500),
            'stripbatch' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{6}'),
            'strip_source' => $this->faker->randomElement([1, 2]),
            'stubesize' => $this->faker->numberBetween(10, 200),
            'stubelength' => $this->faker->numberBetween(3000, 6000),
            'strip_grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'strip_thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'sdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}