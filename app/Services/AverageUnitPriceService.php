<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Outward;
use App\Models\ConsumableInternalName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AverageUnitPriceService
{
    /**
     * Calculate and update the average unit price for a given consumable internal name
     * 
     * @param string $internalName The consumable internal name (from consumable_internal_names->name)
     * @param float $currentQty The current quantity being purchased (pur_qty_int)
     * @param float $currentPrice The current price per unit (pur_rate_int)
     * @param int|null $excludePurchaseId Purchase ID to exclude from calculation (for updates)
     * @return float|null The new average unit price, or null if calculation fails
     */
    public function calculateAndUpdateAveragePrice(string $internalName, float $currentQty, float $currentPrice, ?int $excludePurchaseId = null): ?float
    {
        try {
            // Get the consumable internal name record
            $consumable = ConsumableInternalName::where('name', $internalName)->first();
            
            if (!$consumable) {
                Log::warning("Consumable internal name not found: {$internalName}");
                return null;
            }

            // Get current unit price from consumable_internal_names
            $currentUnitPrice = $consumable->unitPrice ?? 0;

            // If this is an update (excludePurchaseId is provided), we need to reverse out the old values
            if ($excludePurchaseId !== null) {
                // Get the old purchase record to retrieve old qty and price
                $oldPurchase = Purchase::find($excludePurchaseId);
                
                if ($oldPurchase && $oldPurchase->pur_pr_detail_int === $internalName) {
                    $oldQty = $oldPurchase->pur_qty_int ?? 0;
                    $oldPrice = $oldPurchase->pur_rate_int ?? 0;
                    
                    // Calculate balance quantity (excluding the current purchase)
                    $balanceQty = $this->calculateBalanceQuantity($internalName, $excludePurchaseId);
                    
                    // Current total (which includes the old purchase values in the average)
                    $totalQtyWithOld = $balanceQty + $oldQty;
                    $totalValueWithOld = $totalQtyWithOld * $currentUnitPrice;
                    
                    // Reverse out the old contribution
                    $oldContribution = $oldQty * $oldPrice;
                    $valueWithoutOld = $totalValueWithOld - $oldContribution;
                    $qtyWithoutOld = $totalQtyWithOld - $oldQty;
                    
                    // Add new contribution
                    $newTotalValue = $valueWithoutOld + ($currentQty * $currentPrice);
                    $newTotalQty = $qtyWithoutOld + $currentQty;
                    
                    if ($newTotalQty <= 0) {
                        Log::warning("Total quantity is zero or negative for: {$internalName}");
                        return $currentPrice;
                    }
                    
                    $newAveragePrice = $newTotalValue / $newTotalQty;
                    
                    // Update the consumable internal name with new average price
                    $consumable->unitPrice = $newAveragePrice;
                    $consumable->save();
                    
                    Log::info("Updated average price for {$internalName}: {$newAveragePrice} (UPDATE mode)", [
                        'old_qty' => $oldQty,
                        'old_price' => $oldPrice,
                        'new_qty' => $currentQty,
                        'new_price' => $currentPrice,
                        'balance_qty' => $balanceQty,
                        'total_qty_with_old' => $totalQtyWithOld,
                        'total_value_with_old' => $totalValueWithOld,
                        'old_contribution' => $oldContribution,
                        'value_without_old' => $valueWithoutOld,
                        'qty_without_old' => $qtyWithoutOld,
                        'new_total_value' => $newTotalValue,
                        'new_total_qty' => $newTotalQty,
                        'excluded_purchase_id' => $excludePurchaseId
                    ]);
                    
                    return $newAveragePrice;
                } else {
                    Log::warning("Old purchase not found or internal name mismatch for ID: {$excludePurchaseId}");
                    // Fall through to regular calculation
                }
            }
            
            // Regular calculation for new purchases (no excludePurchaseId or if old purchase not found/mismatched)
            // Calculate balance quantity (excluding the current purchase if updating)
            $balanceQty = $this->calculateBalanceQuantity($internalName, $excludePurchaseId);

            // Calculate previous balance value
            $previousBalanceValue = $balanceQty * $currentUnitPrice;

            // Calculate newly entered value
            $newlyEnteredValue = $currentQty * $currentPrice;

            // Calculate new average unit price
            $totalQty = $balanceQty + $currentQty;
            
            if ($totalQty <= 0) {
                Log::warning("Total quantity is zero or negative for: {$internalName}");
                return $currentPrice; // Return current price if no previous stock
            }

            $newAveragePrice = ($previousBalanceValue + $newlyEnteredValue) / $totalQty;

            // Update the consumable internal name with new average price
            $consumable->unitPrice = $newAveragePrice;
            $consumable->save();

            Log::info("Updated average price for {$internalName}: {$newAveragePrice} (CREATE mode)", [
                'balance_qty' => $balanceQty,
                'current_qty' => $currentQty,
                'previous_balance_value' => $previousBalanceValue,
                'newly_entered_value' => $newlyEnteredValue,
                'total_qty' => $totalQty,
                'excluded_purchase_id' => $excludePurchaseId
            ]);

            return $newAveragePrice;

        } catch (\Exception $e) {
            Log::error("Error calculating average price for {$internalName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate balance quantity for a given internal name
     * Balance Qty = Total purchased qty - Total outward qty
     * 
     * @param string $internalName
     * @param int|null $excludePurchaseId Purchase ID to exclude from calculation
     * @return float
     */
    protected function calculateBalanceQuantity(string $internalName, ?int $excludePurchaseId = null): float
    {
        // Get total purchased quantity (pur_qty_int) for this internal name
        $query = Purchase::where('pur_pr_detail_int', $internalName);
        
        // Exclude specific purchase if provided (for update scenarios)
        if ($excludePurchaseId !== null) {
            $query->where('id', '!=', $excludePurchaseId);
        }
        
        $totalPurchased = $query->sum('pur_qty_int') ?? 0;

        // Get total outward quantity (out_qty) for this internal name
        $totalOutward = Outward::where('out_product', $internalName)
            ->sum('out_qty') ?? 0;

        // Calculate balance
        $balance = $totalPurchased - $totalOutward;

        return max(0, $balance); // Ensure non-negative balance
    }

    /**
     * Get current average price for a consumable internal name
     * 
     * @param string $internalName
     * @return float|null
     */
    public function getCurrentAveragePrice(string $internalName): ?float
    {
        $consumable = ConsumableInternalName::where('name', $internalName)->first();
        return $consumable ? $consumable->unitPrice : null;
    }

    /**
     * Get balance quantity for a consumable internal name
     * 
     * @param string $internalName
     * @return float
     */
    public function getBalanceQuantity(string $internalName): float
    {
        return $this->calculateBalanceQuantity($internalName);
    }
}
