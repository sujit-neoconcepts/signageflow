<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\SheetEnquiry;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SheetEnquiryItem>
 */
class SheetEnquiryItemFactory extends Factory
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
                $weightPerSheet = $this->faker->randomFloat(3, 10, 100);
                $requiredWeight = $requiredQty * $weightPerSheet;
                $weight = $qty * $weightPerSheet;
                $soldQty = $this->faker->numberBetween(0, $qty);
                $soldWeight = $soldQty * $weightPerSheet;
        
        return [
            'sheet_enquiry_id' => SheetEnquiry::inRandomOrder()->first()?->id ?? SheetEnquiry::factory(),
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'width' => $this->faker->numberBetween(1000, 2000),
            'slength' => $this->faker->numberBetween(2000, 6000),
            'finish' => $this->faker->randomElement(['2B', 'BA', 'No.4', 'HL', 'Mirror']),
            'required_qty' => $requiredQty,
            'required_weight' => round($requiredWeight, 4),
            'qty' => $qty,
            'weight' => round($weight, 4),
            'sold_qty' => $soldQty,
            'sold_weight' => round($soldWeight, 4),
        ];
    }
}