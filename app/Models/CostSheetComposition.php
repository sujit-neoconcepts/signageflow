<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostSheetComposition extends Model
{
    protected $fillable = [
        'cost_sheet_id',
        'section',
        'consumable_internal_name_id',
        'child_cost_sheet_id',
        'unit',
        'quantity',
        'margin',
    ];

    public function costSheet()
    {
        return $this->belongsTo(CostSheet::class);
    }

    public function consumable()
    {
        return $this->belongsTo(ConsumableInternalName::class, 'consumable_internal_name_id');
    }

    public function childCostSheet()
    {
        return $this->belongsTo(CostSheet::class, 'child_cost_sheet_id');
    }
}
