<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Client;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SheetEnquiry>
 */
class SheetEnquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $enquiryDate = $this->faker->dateTimeBetween('-1 year', 'now');
        
        return [
            'enquiry_number' => null,
            'enquiry_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'dispatch_date' => $this->faker->dateTimeBetween($enquiryDate, '+1 month'),
            'client_id' => Client::inRandomOrder()->first()?->id ?? Client::factory(),
            'status' => $this->faker->randomElement([0, 1, 2, 3]),
        ];
    }
}