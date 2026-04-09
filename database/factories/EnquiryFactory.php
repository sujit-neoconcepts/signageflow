<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Client;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Enquiry>
 */
class EnquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $enquiryDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $dispatchDate = $this->faker->dateTimeBetween($enquiryDate, '+1 month');

        return [
            'enquiry_number' => null, // Will be auto-generated
            'enquiry_date' => $enquiryDate,
            'dispatch_date' => $dispatchDate,
            'client_id' => Client::inRandomOrder()->first()?->id ?? Client::factory(),
            'status' => $this->faker->randomElement([0, 1, 2, 3]), // Open, Partially sold, Sold, Closed
        ];
    }
}
