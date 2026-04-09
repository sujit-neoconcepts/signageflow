<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\ConsumableTransaction;
use App\Models\Tubing;
use App\Models\Consumable;

class ProductionConsumableCostControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function quick_view_allocates_cost_by_produced_weight()
    {
        // seed consumable and locations if needed
        $consumable = Consumable::factory()->create();

        // Create a consumable transaction (out) with total_cost = 1000 on a date
        $date = now()->subDays(10)->format('Y-m-d');
        ConsumableTransaction::factory()->create([
            'consumable_id' => $consumable->id,
            'transaction_type' => 'out',
            'transaction_date' => $date,
            'total_cost' => 1000,
        ]);

        // Create two tubing production chunks with produced weights 60 and 40
        $t1 = Tubing::factory()->create(['weight_produced' => 60, 'production_date' => $date]);
        $t2 = Tubing::factory()->create(['weight_produced' => 40, 'production_date' => $date]);

        $response = $this->getJson(route('production-consumable-cost.quick-view', [
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'stage' => 'tube_making',
            'consumable_id' => $consumable->id,
            'quick_view' => true,
        ]));

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('summary', $json);

        $data = $json['data'];
        $this->assertCount(2, $data);

        $allocatedSum = array_sum(array_column($data, 'allocated_cost'));

        // allow minor float rounding
        $this->assertEqualsWithDelta(1000, $allocatedSum, 0.5);
    }
}
