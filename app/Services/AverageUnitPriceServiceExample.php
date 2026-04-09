<?php

/**
 * Example Usage of AverageUnitPriceService
 * 
 * This file demonstrates how to use the AverageUnitPriceService
 * to calculate and update average unit prices for consumable items.
 */

use App\Services\AverageUnitPriceService;

// Example 1: Calculate average price when creating a new purchase
// This is automatically handled in PurchaseController::store()
function exampleNewPurchase()
{
    $service = new AverageUnitPriceService();
    
    $internalName = 'Welding Rod 3.15mm';
    $currentQty = 50;  // 50 units purchased
    $currentPrice = 12.50;  // at ₹12.50 per unit
    
    // Calculate and update average price
    $newAveragePrice = $service->calculateAndUpdateAveragePrice(
        $internalName,
        $currentQty,
        $currentPrice
    );
    
    echo "New average price for {$internalName}: ₹{$newAveragePrice}\n";
}

// Example 2: Update existing purchase
// This is automatically handled in PurchaseController::update()
function exampleUpdatePurchase()
{
    $service = new AverageUnitPriceService();
    
    $internalName = 'Welding Rod 3.15mm';
    $currentQty = 60;  // Updated to 60 units
    $currentPrice = 13.00;  // Updated to ₹13.00 per unit
    $purchaseId = 123;  // ID of the purchase being updated
    
    // Calculate and update average price (excluding the old values)
    $newAveragePrice = $service->calculateAndUpdateAveragePrice(
        $internalName,
        $currentQty,
        $currentPrice,
        $purchaseId  // Exclude this purchase from balance calculation
    );
    
    echo "Updated average price for {$internalName}: ₹{$newAveragePrice}\n";
}

// Example 3: Get current average price without updating
function exampleGetCurrentPrice()
{
    $service = new AverageUnitPriceService();
    
    $internalName = 'Welding Rod 3.15mm';
    
    $currentPrice = $service->getCurrentAveragePrice($internalName);
    
    if ($currentPrice !== null) {
        echo "Current average price for {$internalName}: ₹{$currentPrice}\n";
    } else {
        echo "No price found for {$internalName}\n";
    }
}

// Example 4: Get balance quantity
function exampleGetBalanceQuantity()
{
    $service = new AverageUnitPriceService();
    
    $internalName = 'Welding Rod 3.15mm';
    
    $balanceQty = $service->getBalanceQuantity($internalName);
    
    echo "Balance quantity for {$internalName}: {$balanceQty} units\n";
}

// Example 5: Complete workflow demonstration
function exampleCompleteWorkflow()
{
    $service = new AverageUnitPriceService();
    $internalName = 'Welding Rod 3.15mm';
    
    echo "=== Complete Workflow Example ===\n\n";
    
    // Step 1: Check current state
    echo "Step 1: Current State\n";
    $currentPrice = $service->getCurrentAveragePrice($internalName);
    $balanceQty = $service->getBalanceQuantity($internalName);
    echo "  Current Average Price: ₹" . ($currentPrice ?? 'N/A') . "\n";
    echo "  Balance Quantity: {$balanceQty} units\n\n";
    
    // Step 2: Add new purchase
    echo "Step 2: Adding New Purchase\n";
    echo "  Purchasing: 100 units @ ₹15.00 per unit\n";
    $newPrice = $service->calculateAndUpdateAveragePrice($internalName, 100, 15.00);
    echo "  New Average Price: ₹{$newPrice}\n\n";
    
    // Step 3: Check updated state
    echo "Step 3: Updated State\n";
    $currentPrice = $service->getCurrentAveragePrice($internalName);
    $balanceQty = $service->getBalanceQuantity($internalName);
    echo "  Current Average Price: ₹{$currentPrice}\n";
    echo "  Balance Quantity: {$balanceQty} units\n";
}

/**
 * Calculation Example with Real Numbers
 * 
 * Scenario:
 * - Existing stock: 200 units @ ₹10.00 per unit (total value: ₹2000)
 * - New purchase: 100 units @ ₹15.00 per unit (total value: ₹1500)
 * 
 * Calculation:
 * - Previous balance value = 200 × 10.00 = ₹2000
 * - Newly entered value = 100 × 15.00 = ₹1500
 * - Total quantity = 200 + 100 = 300 units
 * - New average price = (2000 + 1500) / 300 = ₹11.67 per unit
 */
