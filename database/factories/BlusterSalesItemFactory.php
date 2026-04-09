<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\BlusterSales;
use App\Models\Grade;
use App\Models\Thickness;
use App\Models\CandleSize;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlusterSalesItem>
 */
class BlusterSalesItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(100, 1000);
        $weightPerBluster = $this->faker->randomFloat(3, 0.5, 5);
        $weight = $qty * $weightPerBluster;
        $rate = $this->faker->randomFloat(2, 30, 150);
        $amount = $weight * $rate;

        return [
            'bluster_sales_id' => BlusterSales::inRandomOrder()->first()?->id ?? BlusterSales::factory(),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'candle_size' => CandleSize::inRandomOrder()->first()?->name ?? '50mm',
            'qty' => $qty,
            'rate' => round($rate, 2),
            'amount' => round($qty * $rate, 2),
        ];
    }
}
