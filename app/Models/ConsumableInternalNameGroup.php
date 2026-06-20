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
        $first = \App\Models\ConsumableInternalName::where('consumable_internal_name_group_id', $this->id)->first();
        return $first ? (float)$first->unitPrice : 0.00;
    }

    public function getOpenStockMarginPercentAttribute()
    {
        $first = \App\Models\ConsumableInternalName::where('consumable_internal_name_group_id', $this->id)->first();
        return $first ? (float)$first->openStockMarginPercent : 0.00;
    }

    public static function formInfo()
    {
        return [
            'name' => ['label' => 'Name', 'vRule' => 'required|unique:consumable_internal_name_groups,name', 'searchable' => true, 'sortable' => true],
        ];
    }

    public static function getAllOption()
    {
        return self::orderBy('name')->get()->map(function($g) {
            $firstInternalName = \App\Models\ConsumableInternalName::where('consumable_internal_name_group_id', $g->id)->first();
            
            return [
                'id' => $g->id,
                'label' => $g->name,
                'name' => $g->name, // for search mapping matching option.name or label
                'unitName' => $firstInternalName ? $firstInternalName->unitName : '',
                'unitAltName' => $firstInternalName ? $firstInternalName->unitAltName : '',
                'unitPrice' => $firstInternalName ? (float)$firstInternalName->unitPrice : 0.00,
                'openStockMarginPercent' => $firstInternalName ? (float)$firstInternalName->openStockMarginPercent : 0.00,
            ];
        });
    }
}
