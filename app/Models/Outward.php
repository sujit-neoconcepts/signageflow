<?php

namespace App\Models;

use App\Models\Munit;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\FinancialYearScope;

class Outward extends Model
{
    use HasFactory, FinancialYearScope;
    const DATE_COLUMN = 'out_date';
    protected $fillable = ['out_date', 'out_remark', 'out_incharge', 'out_loc', 'out_product_group', 'out_product', 'out_product_id', 'out_qty', 'out_qty_unit', 'out_qty_alt', 'out_qty_unit_alt', 'unitPrice'];

    public function scopeOutDateStart($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('out_date', '>=', $start->startOfDay());
    }
    public function scopeOutDateEnd($query, $ed)
    {
        $end   = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('out_date', '<=', $end->endOfDay());
    }

    public static function formInfo()
    {

        $formInfo = [
            'out_date' => ['label' => 'Date', 'sortable' => true, 'vRule' => 'required', 'type' => 'datepicker'],
        ];
        return $formInfo;
    }
    public static function formInfoMulti()
    {
        $allunits = Munit::select('name')->orderBy('name')->get()->pluck('name');
        return [
            'out_product' => ['label' => 'Name Internal', 'searchable' => true, 'sortable' => true, 'type' => 'select', 'options' => [], 'vRule' => 'required', 'colspan' => 3],

            'out_incharge' => ['label' => 'Incharge',  'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' =>  User::role('supervisor')->select('name')->orderBy('name')->get()->pluck('name'), 'vRule' => 'required',],

            'out_loc' => ['label' => 'Location', 'searchable' => true, 'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' =>  Location::select('name')->orderBy('name')->get()->pluck('name'), 'vRule' => 'required',],

            'out_product_group' => ['label' => 'Product Group', 'searchable' => true, 'sortable' => true, 'type' => 'select',  'options' => Pgroup::getStockOption(), 'vRule' => 'required', 'colspan' => 3],

            'out_qty' => ['label' => 'Qty', 'searchable' => true, 'sortable' => true, 'vRule' => 'required|numeric', 'align' => 'right', 'showTotal' => true],

            'out_qty_unit' => ['label' => 'Qty Unit',  'sortable' => true,  'vRule' => 'required', 'readonly' => true, 'options' => $allunits,],

            'out_qty_alt' => ['label' => 'Qty Alt', 'searchable' => true, 'sortable' => true, 'align' => 'right', 'showTotal' => true, 'vRule' => 'required',],

            'out_qty_unit_alt' => ['label' => 'Qty Unit Alt',  'sortable' => true,   'readonly' => true, 'vRule' => 'required',],
            'unitPrice' => ['label' => 'Unit Price', 'searchable' => true, 'sortable' => true, 'align' => 'right', 'vRule' => 'nullable|numeric'],
            'out_remark' => ['label' => 'Remark', 'searchable' => true, 'sortable' => true, 'vRule' => 'required'],
        ];
    }
}
