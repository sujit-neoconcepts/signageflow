<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\BlackTubeEnquiry;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlackTubeEnquiryItem>
 */
class BlackTubeEnquiryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requiredQty = $this->faker->numberBetween(100, 1000);
        $qty = $requiredQty + $this->faker->numberBetween(-50, 50);
        $weightPerTube = $this->faker->randomFloat(3, 1, 10);
        $requiredWeight = $requiredQty * $weightPerTube;
        $weight = $qty * $weightPerTube;
        $soldQty = $this->faker->numberBetween(0, $qty);
        $soldWeight = $soldQty * $weightPerTube;

        return [
            'black_tube_enquiry_id' => BlackTubeEnquiry::inRandomOrder()->first()?->id ?? BlackTubeEnquiry::factory(),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'tube_size' => $this->faker->numberBetween(10, 200),
            'tube_length' => $this->faker->numberBetween(3000, 6000),
            'qty' => $qty,
            'weight' => round($weight, 4),
            'sold_qty' => $soldQty,
            'sold_weight' => round($soldWeight, 4),
        ];
    }
}
