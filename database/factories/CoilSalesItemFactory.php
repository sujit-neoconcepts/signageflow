<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CoilSales;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CoilSalesItem>
 */
class CoilSalesItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 10);
        $weightPerCoil = $this->faker->randomFloat(3, 1000, 5000);
        $weight = $qty * $weightPerCoil;
        $rate = $this->faker->randomFloat(2, 80, 250);
        $amount = $weight * $rate;

        return [
            'coil_sales_id' => CoilSales::inRandomOrder()->first()?->id ?? CoilSales::factory(),
            'coil_id' => \App\Models\Coil::inRandomOrder()->first()?->id ?? 1,
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'width' => $this->faker->numberBetween(1000, 2000),
            'weight' => round($weight, 4),
            'rate' => round($rate, 2),
            'amount' => round($amount, 2),
        ];
    }
}
