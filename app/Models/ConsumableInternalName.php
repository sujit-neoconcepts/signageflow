<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumableInternalName extends Model
{
    protected $fillable = ['name', 'consumable_internal_name_group_id', 'unitPrice', 'unitName', 'unitAltName', 'openStockUnit', 'openStockMarginPercent'];

    public function group()
    {
        return $this->belongsTo(ConsumableInternalNameGroup::class, 'consumable_internal_name_group_id');
    }

    public static function formInfo()
    {
        $allunits = \App\Models\Munit::select('name')->orderBy('name')->get()->pluck('name');

        $allgroups = [];
        try {
            $allgroups = \App\Models\ConsumableInternalNameGroup::orderBy('name')->get()->map(function ($g) {
                return ['id' => $g->id, 'label' => $g->name];
            })->toArray();
        } catch (\Exception $e) {
            // Guard against table-not-found during migrations/seeding
        }

        return [
            'name' => ['label' => 'Name', 'vRule' => 'required|unique:consumable_internal_names,name', 'searchable' => true, 'sortable' => true],
            'consumable_internal_name_group_id' => [
                'label' => 'Internal name Group',
                'vRule' => 'nullable|exists:consumable_internal_name_groups,id',
                'type' => 'select',
                'options' => $allgroups,
                'searchable' => false,
                'sortable' => true,
                'addAndRefresh' => true,
            ],
            'unitPrice' => ['label' => 'Unit Price', 'vRule' => 'required|numeric', 'sortable' => true, 'align' => 'right', 'showTotal' => true],
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
