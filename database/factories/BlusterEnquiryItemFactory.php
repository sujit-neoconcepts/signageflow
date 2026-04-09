<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\BlusterEnquiry;
use App\Models\Grade;
use App\Models\Thickness;
use App\Models\CandleSize;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlusterEnquiryItem>
 */
class BlusterEnquiryItemFactory extends Factory
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
        $weightPerBluster = $this->faker->randomFloat(3, 0.5, 5);
        $requiredWeight = $requiredQty * $weightPerBluster;
        $weight = $qty * $weightPerBluster;
        $soldQty = $this->faker->numberBetween(0, $qty);
        $soldWeight = $soldQty * $weightPerBluster;

        return [
            'bluster_enquiry_id' => BlusterEnquiry::inRandomOrder()->first()?->id ?? BlusterEnquiry::factory(),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'candle_size' => CandleSize::inRandomOrder()->first()?->name ?? '50mm',
            'qty' => $qty,
            'required_qty' => $requiredQty,
            'sold_qty' => $soldQty,
        ];
    }
}
