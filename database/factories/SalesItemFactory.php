<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Sales;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalesItem>
 */
class SalesItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(50, 500);
        $weightPerTube = $this->faker->randomFloat(3, 1, 10);
        $weight = $qty * $weightPerTube;
        $rate = $this->faker->randomFloat(2, 50, 200);
        $amount = $weight * $rate;
        $noOfBundle = $this->faker->numberBetween(1, 20);
        $perPcInBundle = ceil($qty / $noOfBundle);

        return [
            'sales_id' => Sales::inRandomOrder()->first()?->id ?? Sales::factory(),
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
