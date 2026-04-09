<?php

namespace App\Services;

use App\Models\ConsumableInternalName;
use App\Models\OpenStockBalance;
use App\Models\OpenStockTransaction;
use App\Models\Outward;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenStockService
{
    public function recordStockInFromOutward(Outward $outward): OpenStockTransaction
    {
        return DB::transaction(function () use ($outward) {
            $config = $this->resolveOpenStockConfig($outward->out_product);
            $qty = $this->resolveOutwardQtyByOpenUnit($outward, $config['open_stock_mode']);

            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'out_qty' => "Open stock quantity must be greater than zero for {$outward->out_product}.",
                ]);
            }

            $balance = $this->lockOrCreateBalance(
                $outward->out_product,
                $outward->out_loc,
                $outward->out_incharge,
                $config['open_stock_unit']
            );

            $balance->qty = (float) $balance->qty + $qty;
            $balance->save();

            return OpenStockTransaction::create([
                'txn_date' => $outward->out_date,
                'transaction_type' => 'in',
                'internal_name' => $outward->out_product,
                'location' => $outward->out_loc,
                'incharge' => $outward->out_incharge,
                'open_stock_unit' => $config['open_stock_unit'],
                'qty' => $qty,
                'base_unit_price' => $config['base_unit_price'],
                'margin_percent' => 0,
                'effective_unit_price' => $config['base_unit_price'],
                'line_amount' => $qty * $config['base_unit_price'],
                'source_type' => 'outward',
                'source_id' => $outward->id,
                'remark' => $outward->out_remark,
            ]);
        });
    }

    public function adjustStock(array $payload): OpenStockTransaction
    {
        return DB::transaction(function () use ($payload) {
            $config = $this->resolveOpenStockConfig($payload['internal_name']);

            $qty = (float) $payload['qty'];
            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'qty' => 'Adjustment quantity must be greater than zero.',
                ]);
            }

            $balance = $this->lockOrCreateBalance(
                $payload['internal_name'],
                $payload['location'],
                $payload['incharge'],
                $config['open_stock_unit']
            );

            if ($payload['adjustment_type'] === 'minus' && (float) $balance->qty < $qty) {
                throw ValidationException::withMessages([
                    'qty' => "Insufficient open stock. Available: {$balance->qty}",
                ]);
            }

            if ($payload['adjustment_type'] === 'plus') {
                $balance->qty = (float) $balance->qty + $qty;
                $transactionType = 'adjustment_plus';
            } else {
                $balance->qty = (float) $balance->qty - $qty;
                $transactionType = 'adjustment_minus';
            }

            $balance->save();

            return OpenStockTransaction::create([
                'txn_date' => $payload['txn_date'],
                'transaction_type' => $transactionType,
                'internal_name' => $payload['internal_name'],
                'location' => $payload['location'],
                'incharge' => $payload['incharge'],
                'open_stock_unit' => $config['open_stock_unit'],
                'qty' => $qty,
                'base_unit_price' => $config['base_unit_price'],
                'margin_percent' => 0,
                'effective_unit_price' => $config['base_unit_price'],
                'line_amount' => $qty * $config['base_unit_price'],
                'source_type' => 'manual_adjustment',
                'remark' => $payload['remark'] ?? null,
            ]);
        });
    }

    public function consumeForSalesOrder(SalesOrder $salesOrder, array $items): array
    {
        $rows = [];
        $subtotalBase = 0;
        $subtotalEffective = 0;

        foreach ($items as $index => $item) {
            $qty = (float) ($item['qty'] ?? 0);
            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.qty" => 'Quantity must be greater than zero.',
                ]);
            }

            $internalName = $item['internal_name'] ?? '';
            $location = $item['location'] ?? '';
            $incharge = $item['incharge'] ?? '';

            $config = $this->resolveOpenStockConfig($internalName);

            $balance = OpenStockBalance::where('internal_name', $internalName)
                ->where('location', $location)
                ->where('incharge', $incharge)
                ->lockForUpdate()
                ->first();

            $availableQty = (float) ($balance->qty ?? 0);

            if (!$balance || $availableQty < $qty) {
                throw ValidationException::withMessages([
                    "items.{$index}.qty" => "Insufficient open stock for {$internalName} at {$location} / {$incharge}. Available: {$availableQty}",
                ]);
            }

            if ($balance->open_stock_unit !== $config['open_stock_unit']) {
                throw ValidationException::withMessages([
                    "items.{$index}.internal_name" => "Open stock unit mismatch for {$internalName}.",
                ]);
            }

            $baseUnitPrice = $config['base_unit_price'];
            $marginPercent = array_key_exists('margin_percent', $item)
                ? max(0, (float) $item['margin_percent'])
                : $config['margin_percent'];
            $effectiveUnitPrice = round($baseUnitPrice * (1 + ($marginPercent / 100)), 4);

            $baseTotal = round($qty * $baseUnitPrice, 2);
            $effectiveTotal = round($qty * $effectiveUnitPrice, 2);

            $balance->qty = $availableQty - $qty;
            $balance->save();

            $transaction = OpenStockTransaction::create([
                'txn_date' => $salesOrder->order_date,
                'transaction_type' => 'out',
                'internal_name' => $internalName,
                'location' => $location,
                'incharge' => $incharge,
                'open_stock_unit' => $config['open_stock_unit'],
                'qty' => $qty,
                'base_unit_price' => $baseUnitPrice,
                'margin_percent' => $marginPercent,
                'effective_unit_price' => $effectiveUnitPrice,
                'line_amount' => $effectiveTotal,
                'source_type' => 'salesOrder',
                'source_id' => $salesOrder->id,
                'remark' => $this->buildSalesOrderTxnRemark($salesOrder),
            ]);

            $rows[] = [
                'internal_name' => $internalName,
                'location' => $location,
                'incharge' => $incharge,
                'qty' => $qty,
                'open_stock_unit' => $config['open_stock_unit'],
                'base_unit_price' => $baseUnitPrice,
                'margin_percent' => $marginPercent,
                'effective_unit_price' => $effectiveUnitPrice,
                'base_total' => $baseTotal,
                'effective_total' => $effectiveTotal,
                'open_stock_transaction_id' => $transaction->id,
            ];

            $subtotalBase += $baseTotal;
            $subtotalEffective += $effectiveTotal;
        }

        return [
            'rows' => $rows,
            'subtotal_base' => round($subtotalBase, 2),
            'subtotal_effective' => round($subtotalEffective, 2),
        ];
    }

    public function reverseSalesOrder(SalesOrder $salesOrder): void
    {
        $salesOrder->loadMissing('items');

        foreach ($salesOrder->items as $item) {
            $balance = $this->lockOrCreateBalance(
                $item->internal_name,
                $item->location,
                $item->incharge,
                $item->open_stock_unit
            );

            $balance->qty = (float) $balance->qty + (float) $item->qty;
            $balance->save();

            OpenStockTransaction::create([
                'txn_date' => $salesOrder->order_date,
                'transaction_type' => 'reverse_out',
                'internal_name' => $item->internal_name,
                'location' => $item->location,
                'incharge' => $item->incharge,
                'open_stock_unit' => $item->open_stock_unit,
                'qty' => $item->qty,
                'base_unit_price' => $item->base_unit_price,
                'margin_percent' => $item->margin_percent,
                'effective_unit_price' => $item->effective_unit_price,
                'line_amount' => $item->effective_total,
                'source_type' => 'salesOrderReverse',
                'source_id' => $salesOrder->id,
                'source_item_id' => $item->id,
                'remark' => 'Reversal for Sales Order ' . $salesOrder->order_no,
            ]);
        }
    }

    protected function resolveOpenStockConfig(string $internalName): array
    {
        $consumable = ConsumableInternalName::where('name', $internalName)->first();

        if (!$consumable) {
            throw ValidationException::withMessages([
                'internal_name' => "Internal name {$internalName} is not configured in Product Internal Name.",
            ]);
        }

        $openStockMode = ((int) ($consumable->openStockUnit ?? 0)) === 1 ? 1 : 0;
        if ($openStockMode === 1 && empty($consumable->unitAltName)) {
            throw ValidationException::withMessages([
                'open_stock_unit' => "Open stock unit is set to Alternative for {$internalName}, but alternative unit is empty.",
            ]);
        }
        $openStockUnit = $openStockMode === 1 ? $consumable->unitAltName : $consumable->unitName;
        $baseUnitPrice = (float) ($consumable->unitPrice ?? 0);
        $marginPercent = (float) ($consumable->openStockMarginPercent ?? 0);

        return [
            'consumable' => $consumable,
            'open_stock_mode' => $openStockMode,
            'open_stock_unit' => $openStockUnit,
            'base_unit_price' => $baseUnitPrice,
            'margin_percent' => $marginPercent,
        ];
    }

    protected function resolveOutwardQtyByOpenUnit(Outward $outward, int $openStockMode): float
    {
        if ($openStockMode === 1) {
            return (float) ($outward->out_qty_alt ?? 0);
        }

        return (float) ($outward->out_qty ?? 0);
    }

    protected function lockOrCreateBalance(string $internalName, string $location, string $incharge, string $openStockUnit): OpenStockBalance
    {
        $balance = OpenStockBalance::where('internal_name', $internalName)
            ->where('location', $location)
            ->where('incharge', $incharge)
            ->lockForUpdate()
            ->first();

        if (!$balance) {
            return OpenStockBalance::create([
                'internal_name' => $internalName,
                'location' => $location,
                'incharge' => $incharge,
                'open_stock_unit' => $openStockUnit,
                'qty' => 0,
            ]);
        }

        if ($balance->open_stock_unit !== $openStockUnit) {
            throw ValidationException::withMessages([
                'open_stock_unit' => "Open stock unit mismatch for {$internalName}. Existing: {$balance->open_stock_unit}, configured: {$openStockUnit}",
            ]);
        }

        return $balance;
    }

    protected function buildSalesOrderTxnRemark(SalesOrder $salesOrder): string
    {
        $parts = [
            'Order No: ' . ($salesOrder->order_no ?? ''),
            'Project: ' . ($salesOrder->project_name ?? ''),
            'Product: ' . ($salesOrder->product_name ?? ''),
        ];

        $base = implode(' | ', $parts);
        $userRemark = trim((string) ($salesOrder->remark ?? ''));

        return $userRemark !== '' ? $base . ' | Remark: ' . $userRemark : $base;
    }
}
