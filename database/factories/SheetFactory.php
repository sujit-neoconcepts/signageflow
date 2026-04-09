<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Supplier;
use App\Models\Grade;
use App\Models\Thickness;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sheet>
 */
class SheetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'rdate' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'supplier_id' => Supplier::inRandomOrder()->first()?->id ?? 1,
            'grade' => Grade::inRandomOrder()->first()?->name ?? '304',
            'thickness' => Thickness::inRandomOrder()->first()?->tvalue ?? 1.0,
            'width' => $this->faker->numberBetween(100, 2000),
            'slength' => $this->faker->numberBetween(1000, 6000),
            'finish' => $this->faker->word(),
            'qty' => $this->faker->numberBetween(10, 1000),
            'weight' => $this->faker->randomFloat(3, 10, 1000),
            'invoice_num' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{6}'),
            'status' => $this->faker->randomElement([0, 1, 2]),
            'sheet_batch' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{6}'),
        ];
    }
}