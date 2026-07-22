<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumableInternalNameGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected $appends = ['unitName', 'unitAltName', 'unitPrice', 'openStockMarginPercent'];

    public function items()
    {
        return $this->hasMany(ConsumableInternalName::class, 'consumable_internal_name_group_id');
    }

    public function getUnitNameAttribute()
    {
        $first = $this->items->first();

        return $first ? $first->unitName : '';
    }

    public function getUnitAltNameAttribute()
    {
        $first = $this->items->first();

        return $first ? $first->unitAltName : '';
    }

    public function getUnitPriceAttribute()
    {
        $prices = self::getWeightedUnitPrices(collect([$this]));

        return $prices[$this->id] ?? 0.00;
    }

    public function getOpenStockMarginPercentAttribute()
    {
        $first = $this->items->first();

        return $first ? (float) $first->openStockMarginPercent : 0.00;
    }

    public static function formInfo()
    {
        return [
            'name' => ['label' => 'Name', 'vRule' => 'required|unique:consumable_internal_name_groups,name', 'searchable' => true, 'sortable' => true],
        ];
    }

    /**
     * Calculate weighted unit prices for a list or collection of groups.
     * Formula: sum(current stock * unit price) / sum(current stock of items with unit price > 0)
     * If total stock is 0 or negative, returns 0.00 as requested.
     * Note: Items with unitPrice <= 0 are excluded from calculations (e.g. non-zero avg: $items->where('unitPrice', '>', 0)->avg('unitPrice')).
     */
    public static function getWeightedUnitPrices($groups = null)
    {
        if ($groups === null) {
            $groups = self::with('items')->get();
        } elseif ($groups instanceof \Illuminate\Database\Eloquent\Builder || $groups instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
            $groups = $groups->with('items')->get();
        }

        $allItems = $groups->pluck('items')->flatten();
        $itemNames = $allItems->pluck('name')->filter()->unique()->toArray();

        $pur0Map = [];
        $pur1Map = [];
        $outMap = [];

        if (! empty($itemNames)) {
            $pur0Map = \App\Models\Purchase::select('pur_pr_detail_int', \DB::raw('IFNULL(SUM(pur_qty_int), 0) as qtysum'))
                ->where('entry_type', 0)
                ->whereIn('pur_pr_detail_int', $itemNames)
                ->groupBy('pur_pr_detail_int')
                ->pluck('qtysum', 'pur_pr_detail_int');

            $pur1Map = \App\Models\Purchase::select('pur_pr_detail_int', \DB::raw('IFNULL(SUM(pur_qty_int), 0) as qtysum'))
                ->where('entry_type', 1)
                ->whereIn('pur_pr_detail_int', $itemNames)
                ->groupBy('pur_pr_detail_int')
                ->pluck('qtysum', 'pur_pr_detail_int');

            $outMap = \App\Models\Outward::select('out_product', \DB::raw('IFNULL(SUM(out_qty), 0) as qtysum'))
                ->whereIn('out_product', $itemNames)
                ->groupBy('out_product')
                ->pluck('qtysum', 'out_product');
        }

        $result = [];

        foreach ($groups as $g) {
            $items = $g->items;
            if ($items->isEmpty()) {
                $result[$g->id] = 0.00;

                continue;
            }

            $weightedSum = 0;
            $totalStock = 0;

            foreach ($items as $item) {
                $unitPrice = (float) $item->unitPrice;
                if ($unitPrice <= 0) {
                    continue; // Exclude items with 0 unit price
                }

                $qtyPur0 = (float) ($pur0Map[$item->name] ?? 0);
                $qtyPur1 = (float) ($pur1Map[$item->name] ?? 0);
                $qtyOut = (float) ($outMap[$item->name] ?? 0);
                $stock = $qtyPur1 + $qtyPur0 - $qtyOut;

                if ($stock > 0) {
                    $weightedSum += $stock * $unitPrice;
                    $totalStock += $stock;
                }
            }

            if ($totalStock > 0) {
                $result[$g->id] = (float) ($weightedSum / $totalStock);
            } else {
                // Total stock is 0: display 0.00 as requested.
                // (If non-zero arithmetic average is ever needed: (float) ($items->where('unitPrice', '>', 0)->avg('unitPrice') ?? 0.00))
                $result[$g->id] = 0.00;
            }
        }

        return $result;
    }

    public static function getAllOption()
    {
        $groups = self::with('items')->orderBy('name')->get();
        $weightedPrices = self::getWeightedUnitPrices($groups);

        return $groups->map(function ($g) use ($weightedPrices) {
            $firstInternalName = $g->items->first();
            $unitPrice = $weightedPrices[$g->id] ?? 0.00;

            return [
                'id' => $g->id,
                'label' => $g->name,
                'name' => $g->name, // for search mapping matching option.name or label
                'unitName' => $firstInternalName ? $firstInternalName->unitName : '',
                'unitAltName' => $firstInternalName ? $firstInternalName->unitAltName : '',
                'unitPrice' => (float) $unitPrice,
                'openStockMarginPercent' => $firstInternalName ? (float) $firstInternalName->openStockMarginPercent : 0.00,
            ];
        });
    }
}
