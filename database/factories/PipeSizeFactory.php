<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PipeSize>
 */
class PipeSizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slitted_size_id' => \App\Models\SlittedSize::inRandomOrder()->first()?->id ?? 1,
            'pipe_type' => $this->faker->randomElement([1, 2, 3]), // 1=Round, 2=Square, 3=Rectangle
            'ln' => $this->faker->numberBetween(10, 200),
            'ht' => $this->faker->numberBetween(10, 200),
        ];
    }
}
