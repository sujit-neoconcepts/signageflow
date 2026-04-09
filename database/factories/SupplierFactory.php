<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sp_name' => $this->faker->word(),
            'sp_addr' => $this->faker->address(),
            'sp_phn' => $this->faker->phoneNumber(),
            'sp_email' => $this->faker->unique()->safeEmail(),
            'sp_gst' => $this->faker->word(),
        ];
    }
}