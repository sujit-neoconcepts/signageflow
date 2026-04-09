<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumableInternalName extends Model
{
    protected $fillable = ['name', 'unitPrice', 'unitName', 'unitAltName', 'openStockUnit', 'openStockMarginPercent'];

    public static function formInfo()
    {
        $allunits = \App\Models\Munit::select('name')->orderBy('name')->get()->pluck('name');

        return [
            'name' => ['label' => 'Name', 'vRule' => 'required|unique:consumable_internal_names,name', 'searchable' => true, 'sortable' => true],
            'unitPrice' => ['label' => 'Unit Price', 'vRule' => 'required|numeric', 'sortable' => true],
            'unitName' => ['label' => 'Unit Name', 'vRule' => 'required', 'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' => $allunits],
            'unitAltName' => ['label' => 'Unit Alt Name', 'vRule' => 'nullable', 'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' => $allunits],
            'openStockUnit' => [
                'label' => 'Open Stock Unit',
                'vRule' => 'required',
                'sortable' => true,
                'type' => 'select',
                'options' => [
                    ['id' => 0, 'label' => 'Main [0]'],
                    ['id' => 1, 'label' => 'Alternative [1]'],
                ],
                'default' => ['id' => 0, 'label' => 'Main [0]'],
            ],
            'openStockMarginPercent' => ['label' => 'Open Stock Margin %', 'vRule' => 'required|numeric|min:0', 'sortable' => true],
        ];
    }
}
