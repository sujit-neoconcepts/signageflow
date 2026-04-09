<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\SheetSales;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SheetSalesItem>
 */
class SheetSalesItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(10, 100);
                $weightPerSheet = $this->faker->randomFloat(3, 10, 100);
                $weight = $qty * $weightPerSheet;
                $rate = $this->faker->randomFloat(2, 50, 200);
                $amount = $weight * $rate;
        
        return [
            'sheet_sales_id' => SheetSales::inRandomOrder()->first()?->id ?? SheetSales::factory(),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'width' => $this->faker->numberBetween(1000, 2000),
            'slength' => $this->faker->numberBetween(2000, 6000),
            'finish' => $this->faker->randomElement(['2B', 'BA', 'No.4', 'HL', 'Mirror']),
            'qty' => $qty,
            'weight' => round($weight, 4),
            'rate' => round($rate, 2),
            'amount' => round($amount, 2),
        ];
    }
}