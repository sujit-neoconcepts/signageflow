<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumableInternalNameGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected $appends = ['unitName', 'unitAltName', 'unitPrice', 'openStockMarginPercent'];

    public function getUnitNameAttribute()
    {
        $first = \App\Models\ConsumableInternalName::where('consumable_internal_name_group_id', $this->id)->first();

        return $first ? $first->unitName : '';
    }

    public function getUnitAltNameAttribute()
    {
        $first = \App\Models\ConsumableInternalName::where('consumable_internal_name_group_id', $this->id)->first();

        return $first ? $first->unitAltName : '';
    }

    public function getUnitPriceAttribute()
    {
        $items = \App\Models\ConsumableInternalName::where('consumable_internal_name_group_id', $this->id)->get();

        if ($items->isEmpty()) {
            return 0.00;
        }

        $sum = $items->sum(function ($item) {
            return $item->unitPrice * (1 + $item->openStockMarginPercent / 100);
        });

        return (float) ($sum / $items->count());
    }

    public function getOpenStockMarginPercentAttribute()
    {
        return 0.00;
    }

    public static function formInfo()
    {
        return [
            'name' => ['label' => 'Name', 'vRule' => 'required|unique:consumable_internal_name_groups,name', 'searchable' => true, 'sortable' => true],
        ];
    }

    public static function getAllOption()
    {
        return self::orderBy('name')->get()->map(function ($g) {
            $firstInternalName = \App\Models\ConsumableInternalName::where('consumable_internal_name_group_id', $g->id)->first();
            $items = \App\Models\ConsumableInternalName::where('consumable_internal_name_group_id', $g->id)->get();
            $averagePrice = 0.00;

            if (! $items->isEmpty()) {
                $sum = $items->sum(function ($item) {
                    return $item->unitPrice * (1 + $item->openStockMarginPercent / 100);
                });
                $averagePrice = $sum / $items->count();
            }

            return [
                'id' => $g->id,
                'label' => $g->name,
                'name' => $g->name, // for search mapping matching option.name or label
                'unitName' => $firstInternalName ? $firstInternalName->unitName : '',
                'unitAltName' => $firstInternalName ? $firstInternalName->unitAltName : '',
                'unitPrice' => (float) $averagePrice,
                'openStockMarginPercent' => 0.00,
            ];
        });
    }
}
