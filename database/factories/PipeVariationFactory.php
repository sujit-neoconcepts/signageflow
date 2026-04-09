<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PipeVariation>
 */
class PipeVariationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variation_code' => $this->faker->regexify('[A-Z]{2}[0-9]{4}'),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'tlength' => $this->faker->numberBetween(1000, 6000),
            'tube_size' => $this->faker->numberBetween(1, 100),
            't_wt' => $this->faker->randomFloat(3, 1, 10),
            'b_qty' => $this->faker->numberBetween(10, 1000),
            'm_wt' => $this->faker->randomFloat(3, 10, 1000),
        ];
    }
}
