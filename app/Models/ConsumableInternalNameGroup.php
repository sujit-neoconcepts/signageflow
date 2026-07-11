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
        $items = $this->items;

        if ($items->isEmpty()) {
            return 0.00;
        }

        return (float) $items->avg('unitPrice');
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

    public static function getAllOption()
    {
        return self::orderBy('name')->get()->map(function ($g) {
            $firstInternalName = \App\Models\ConsumableInternalName::where('consumable_internal_name_group_id', $g->id)->first();
            $items = \App\Models\ConsumableInternalName::where('consumable_internal_name_group_id', $g->id)->get();
            $averagePrice = 0.00;

            if (! $items->isEmpty()) {
                $averagePrice = $items->avg('unitPrice');
            }

            return [
                'id' => $g->id,
                'label' => $g->name,
                'name' => $g->name, // for search mapping matching option.name or label
                'unitName' => $firstInternalName ? $firstInternalName->unitName : '',
                'unitAltName' => $firstInternalName ? $firstInternalName->unitAltName : '',
                'unitPrice' => (float) $averagePrice,
                'openStockMarginPercent' => $firstInternalName ? (float) $firstInternalName->openStockMarginPercent : 0.00,
            ];
        });
    }
}
