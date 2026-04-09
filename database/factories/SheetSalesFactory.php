<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Client;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SheetSales>
 */
class SheetSalesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salesDate = $this->faker->dateTimeBetween('-1 year', 'now');
                $deliveryDate = $this->faker->dateTimeBetween($salesDate, '+1 month');
                $subTotal = $this->faker->randomFloat(2, 10000, 100000);
                $taxTotal = $subTotal * 0.18;
                $total = $subTotal + $taxTotal;
        
        return [
            'sales_number' => null,
            'sales_date' => $salesDate,
            'delivery_date' => $deliveryDate,
            'client_id' => Client::inRandomOrder()->first()?->id ?? Client::factory(),
            'vehicle_number' => $this->faker->regexify('[A-Z]{2}[0-9]{2}[A-Z]{2}[0-9]{4}'),
            'invoice_number' => $this->faker->unique()->regexify('INV-[0-9]{8}'),
            'sub_total' => round($subTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($total, 2),
            'notes' => $this->faker->optional()->sentence(),
            'status' => $this->faker->randomElement([0, 1, 2, 3]),
        ];
    }
}