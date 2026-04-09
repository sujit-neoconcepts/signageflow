<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BlusterPipeItem;
use App\Models\BlusterShortlengthItem;

class RecalculateBlusterConsumptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bluster:recalculate-consumptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate consumed_qty and consumed_weight for all bluster allocations from consumption records to fix floating-point precision issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Recalculating consumed quantities for pipe allocations...');
        
        $pipeAllocations = BlusterPipeItem::allocations()->get();
        $pipeCount = 0;
        
        foreach ($pipeAllocations as $allocation) {
            $oldQty = $allocation->consumed_qty;
            $oldWeight = $allocation->consumed_weight;
            
            $allocation->recalculateConsumed();
            $allocation->refresh();
            
            if ($oldQty != $allocation->consumed_qty || $oldWeight != $allocation->consumed_weight) {
                $this->line(" - Allocation #{$allocation->id}: qty {$oldQty} -> {$allocation->consumed_qty}, weight {$oldWeight} -> {$allocation->consumed_weight}");
                $pipeCount++;
            }
        }
        
        $this->info("Fixed {$pipeCount} pipe allocations.");
        
        $this->info('Recalculating consumed quantities for shortlength allocations...');
        
        $shortlengthAllocations = BlusterShortlengthItem::allocations()->get();
        $shortlengthCount = 0;
        
        foreach ($shortlengthAllocations as $allocation) {
            $oldQty = $allocation->consumed_qty;
            $oldWeight = $allocation->consumed_weight;
            
            $allocation->recalculateConsumed();
            $allocation->refresh();
            
            if ($oldQty != $allocation->consumed_qty || $oldWeight != $allocation->consumed_weight) {
                $this->line(" - Allocation #{$allocation->id}: qty {$oldQty} -> {$allocation->consumed_qty}, weight {$oldWeight} -> {$allocation->consumed_weight}");
                $shortlengthCount++;
            }
        }
        
        $this->info("Fixed {$shortlengthCount} shortlength allocations.");
        
        $this->info('Recalculation complete!');
        
        return Command::SUCCESS;
    }
}
