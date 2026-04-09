<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PolishingDetail;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Packing>
 */
class PackingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'polishing_detail_id' => PolishingDetail::inRandomOrder()->first()?->id ?? PolishingDetail::factory(),
            'pkpdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'pkfdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'tubesize' => $this->faker->numberBetween(10, 200),
            'tubelength' => $this->faker->numberBetween(3000, 6000),
            'batch_num' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{6}'),
            'preperedby' => $this->faker->name(),
            'total_tubes_given' => $this->faker->numberBetween(50, 500),
            'total_weight_given' => $this->faker->randomFloat(3, 100, 1000),
            'total_tubes_packed' => $this->faker->numberBetween(50, 500),
            'total_weight_packed' => $this->faker->randomFloat(3, 100, 1000),
            'packing_status' => $this->faker->randomElement([0, 1, 2]),
            'comment' => $this->faker->optional()->sentence(),
        ];
    }
}
