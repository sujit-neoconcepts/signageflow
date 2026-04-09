<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostSheet extends Model
{
    use HasFactory;

    protected $fillable = ['prod_type', 'name', 'qty_unit', 'alt_units', 'rate'];

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
