<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CoilEnquiry;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CoilEnquiryItem>
 */
class CoilEnquiryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requiredQty = $this->faker->numberBetween(1, 10);
        $qty = $requiredQty + $this->faker->numberBetween(-1, 1);
        $weightPerCoil = $this->faker->randomFloat(3, 1000, 5000);
        $requiredWeight = $requiredQty * $weightPerCoil;
        $weight = $qty * $weightPerCoil;
        $soldQty = $this->faker->numberBetween(0, $qty);
        $soldWeight = $soldQty * $weightPerCoil;

        return [
            'coil_enquiry_id' => CoilEnquiry::inRandomOrder()->first()?->id ?? CoilEnquiry::factory(),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'width' => $this->faker->numberBetween(1000, 2000),
            'required_weight' => round($requiredWeight, 4),
            'weight' => round($weight, 4),
            'sold_weight' => round($soldWeight, 4),
        ];
    }
}
