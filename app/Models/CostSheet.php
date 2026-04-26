<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostSheet extends Model
{
    use HasFactory;

    protected $fillable = ['prod_type', 'name', 'no_of_unit', 'qty_unit', 'alt_units', 'rate'];

    protected $appends = ['total_cost'];

    public function getTotalCostAttribute()
    {
        return $this->compositions->reduce(function ($total, $composition) {
            $quantity = $composition->quantity ?? 0;
            $margin = $composition->margin ?? 0;
            $unitPrice = 0;

            if ($composition->section === 'raw_material' && $composition->consumable) {
                $basePrice = $composition->consumable->unitPrice ?? 0;
                $consumableMargin = $composition->consumable->openStockMarginPercent ?? 0;
                $unitPrice = $basePrice * (1 + $consumableMargin / 100);
            } elseif ($composition->childCostSheet) {
                $child = $composition->childCostSheet;
                $cost = $child->total_cost ?: 0;
                $units = $child->no_of_unit ?: 1;
                $perUnitCost = $cost / $units;
                $unitPrice = $perUnitCost > 0 ? $perUnitCost : ($child->rate ?: 0);
            }

            $subTotal = $unitPrice * $quantity;
            $withMargin = $subTotal * (1 + $margin / 100);

            return $total + $withMargin;
        }, 0);
    }

    public function compositions()
    {
        return $this->hasMany(CostSheetComposition::class);
    }

    public static function formInfo(): array
    {
        $allunits = Munit::select('name')->orderBy('name')->pluck('name');

        return [
            'name' => [
                'label' => 'Name',
                'searchable' => true,
                'sortable' => true,
                'vRule' => 'required|max:255',
            ],
            'no_of_unit' => [
                'label' => 'No of Unit',
                'vRule' => 'required|integer|min:1',
                'default' => 1,
            ],
            'qty_unit' => [
                'label' => 'Qty Unit',
                'searchable' => true,
                'sortable' => true,
                'type' => 'select',
                'optionType' => 'array',
                'options' => $allunits,
                'vRule' => 'required',
            ],
            'alt_units' => [
                'label' => 'Alt Units',
                'searchable' => true,
                'sortable' => true,
                'type' => 'select',
                'optionType' => 'array',
                'options' => $allunits,
            ],
            'rate' => [
                'label' => 'Rate',
                'searchable' => true,
                'sortable' => true,
                'vRule' => 'required|numeric|min:0',
                'align' => 'right',
            ],
        ];
    }
}
