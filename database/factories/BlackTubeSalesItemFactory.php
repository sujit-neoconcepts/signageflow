<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\BlackTubeSales;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlackTubeSalesItem>
 */
class BlackTubeSalesItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(100, 1000);
                $weightPerTube = $this->faker->randomFloat(3, 1, 10);
                $weight = $qty * $weightPerTube;
                $noOfBundle = $this->faker->numberBetween(1, 20);
                $perPcInBundle = ceil($qty / $noOfBundle);
                $rate = $this->faker->randomFloat(2, 40, 160);
                $amount = $weight * $rate;
        
        return [
            'black_tube_sales_id' => BlackTubeSales::inRandomOrder()->first()?->id ?? BlackTubeSales::factory(),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'tube_size' => $this->faker->numberBetween(10, 200),
            'tube_length' => $this->faker->numberBetween(3000, 6000),
            'qty' => $qty,
            'weight' => round($weight, 4),
            'no_of_bundle' => $noOfBundle,
            'per_pc_in_bundle' => $perPcInBundle,
            'rate' => round($rate, 2),
            'amount' => round($amount, 2),
        ];
    }
}