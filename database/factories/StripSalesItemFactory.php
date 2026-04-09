<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\StripSales;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StripSalesItem>
 */
class StripSalesItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(10, 100);
        $weightPerStrip = $this->faker->randomFloat(3, 50, 500);
        $weight = $qty * $weightPerStrip;
        $rate = $this->faker->randomFloat(2, 60, 180);
        $amount = $weight * $rate;

        return [
            'strip_sales_id' => StripSales::inRandomOrder()->first()?->id ?? StripSales::factory(),
            'strip_id' => \App\Models\Strip::inRandomOrder()->first()?->id ?? 1,
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'width' => $this->faker->numberBetween(50, 500),
            'weight' => round($weight, 4),
            'rate' => round($rate, 2),
            'amount' => round($amount, 2),
        ];
    }
}
