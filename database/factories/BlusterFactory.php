<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bluster>
 */
class BlusterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'candle_size' => $this->faker->numberBetween(1, 100),
            'qty' => $this->faker->numberBetween(10, 1000),
            'status' => $this->faker->randomElement([0, 1, 2]),
        ];
    }
}