<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cl_name' => $this->faker->word(),
            'contact_person' => $this->faker->word(),
            'cl_addr' => $this->faker->address(),
            'cl_addr2' => $this->faker->address(),
            'pincode' => $this->faker->regexify('[A-Z]{2}[0-9]{4}'),
            'cl_phn' => $this->faker->phoneNumber(),
            'cl_email' => $this->faker->unique()->safeEmail(),
            'password' => 'password',
            'remember_token' => $this->faker->word(),
            'cl_gst' => $this->faker->word(),
            'active' => $this->faker->boolean(80),
            'preference' => $this->faker->word(),
        ];
    }
}