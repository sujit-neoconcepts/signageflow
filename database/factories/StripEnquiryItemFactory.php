<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\StripEnquiry;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StripEnquiryItem>
 */
class StripEnquiryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requiredQty = $this->faker->numberBetween(10, 100);
        $qty = $requiredQty + $this->faker->numberBetween(-5, 5);
        $weightPerStrip = $this->faker->randomFloat(3, 50, 500);
        $requiredWeight = $requiredQty * $weightPerStrip;
        $weight = $qty * $weightPerStrip;
        $soldQty = $this->faker->numberBetween(0, $qty);
        $soldWeight = $soldQty * $weightPerStrip;

        return [
            'strip_enquiry_id' => StripEnquiry::inRandomOrder()->first()?->id ?? StripEnquiry::factory(),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'strip_width' => $this->faker->numberBetween(50, 500),
            'weight' => round($weight, 4),
            'sold_weight' => round($soldWeight, 4),
        ];
    }
}
