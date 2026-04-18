<?php

namespace App\Models;

use App\Models\Munit;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\FinancialYearScope;

class Purchase extends Model
{
    use HasFactory, FinancialYearScope;
    const DATE_COLUMN = 'pur_date';
    protected $fillable = ['purchase_info_id', 'pur_date', 'received_date', 'pur_inv', 'pur_supplier', 'pur_pr_id', 'pur_pr_detail', 'pur_pr_hsn', 'pur_pr_detail_int', 'pur_qty', 'pur_qty_int', 'pur_unit', 'pur_unint_int', 'pur_gst', 'pur_amnt', 'pur_gst_amnt', 'pur_amnt_total', 'pur_rate', 'pur_rate_int', 'pur_qty_alt', 'pur_unit_alt', 'pur_qty_int_alt', 'pur_unint_int_alt', 'pur_unit_conv_rate', 'pur_incharge', 'pur_loc', 'entry_type', 'remark'];

    public function purchaseInfo()
    {
        return $this->belongsTo(PurchaseInfo::class, 'purchase_info_id');
    }

    public function scopePurDateStart($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('pur_date', '>=', $start->startOfDay());
    }
    public function scopePurDateEnd($query, $ed)
    {
        $end   = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('pur_date', '<=', $end->endOfDay());
    }

    public function scopeReceivedDateStart($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('received_date', '>=', $start->startOfDay());
    }
    public function scopeReceivedDateEnd($query, $ed)
    {
        $end   = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('received_date', '<=', $end->endOfDay());
    }
    public static function formInfo()
    {
        $allsuppliers = Supplier::select('sp_name')->orderBy('sp_name')->get()->pluck('sp_name');
        $formInfo = [
            'pur_date' => ['label' => 'Purchase Date', 'sortable' => true, 'vRule' => 'required', 'type' => 'datepicker'],
            'received_date' => ['label' => 'Received Date', 'sortable' => true, 'vRule' => 'nullable', 'type' => 'datepicker'],
            'pur_inv' => ['label' => 'Invoice No', 'searchable' => true, 'sortable' => true, 'vRule' => 'required|unique:purchases_info,pur_inv'],
            'pur_supplier' => ['label' => 'Supplier Name',  'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' => $allsuppliers, 'vRule' => 'required',],
            'roundoff' => ['label' => 'Roundoff (+/-)', 'sortable' => true, 'vRule' => 'nullable|numeric', 'type' => 'number', 'align' => 'right', 'default' => '0'],
        ];
        return $formInfo;
    }
    public static function formInfoMulti()
    {
        $allunits = Munit::select('name')->orderBy('name')->get()->pluck('name');
        $formInfo = [
            'pur_pr_detail' => [
                'label' => 'Name As Per Invoice', 
                'searchable' => true, 
                'sortable' => true, 
                'type' => 'select', 
                'options' => Product::getAllOption(), 
                'vRule' => 'required', 
                'colspan' => 3, 
                'addAndRefresh' => true,
                'autoFill' => [
                    'pur_pr_hsn' => 'data.pr_hsn',
                    'pur_pr_detail_int' => 'data.pr_detail_int',
                    'pur_unit' => 'data.pr_pur_unit',
                    'pur_unit_alt' => 'data.pr_pur_unit_alt',
                    'pur_unint_int' => 'data.pr_int_unit',
                    'pur_unint_int_alt' => 'data.pr_int_unit_alt',
                    'pur_unit_conv_rate' => 'data.pr_min_unit',
                    'pur_gst' => 'data.pr_gst_rate',
                    'last_rate' => 'data.last_rate',
                    'unit_rate' => 'data.unit_rate',
                    'available_qty' => 'data.available_qty'
                ]
            ],

            'pur_pr_hsn' => ['label' => 'HSN Code', 'searchable' => true, 'sortable' => true, 'readonly' => true],
            
            'last_rate' => ['label' => 'Last Rate', 'readonly' => true, 'align' => 'right', 'color' => 'bg-yellow-100/50 dark:bg-yellow-900/40 text-blue-600 dark:text-blue-400 font-bold'],
            'unit_rate' => ['label' => 'Unit Rate', 'readonly' => true, 'align' => 'right', 'color' => 'bg-yellow-100/50 dark:bg-yellow-900/40 text-blue-600 dark:text-blue-400 font-bold'],
            'available_qty' => ['label' => 'Available Qty', 'readonly' => true, 'align' => 'right', 'color' => 'bg-yellow-100/50 dark:bg-yellow-900/40 text-blue-600 dark:text-blue-400 font-bold'],

            'pur_pr_detail_int' => ['label' => 'Internal Name', 'searchable' => true, 'sortable' => true, 'vRule' => 'required', 'readonly' => true, 'colspan' => 2],

            'pur_incharge' => ['label' => 'Incharge',  'sortable' => true, 'vRule' => 'required', 'type' => 'select', 'optionType' => 'array', 'options' =>  User::role('supervisor')->select('name')->orderBy('name')->get()->pluck('name'),],

            'pur_loc' => ['label' => 'Location', 'searchable' => true, 'sortable' => true, 'vRule' => 'required',  'type' => 'select', 'optionType' => 'array', 'options' =>  Location::select('name')->orderBy('name')->get()->pluck('name'),],

            'pur_qty' => ['label' => 'Billed Qty', 'searchable' => true, 'sortable' => true, 'vRule' => 'required|numeric', 'align' => 'right', 'showTotal' => true],

            'pur_unit' => ['label' => 'Qty Unit',  'sortable' => true,  'vRule' => 'required', 'readonly' => true, 'options' => $allunits,],

            'pur_qty_alt' => ['label' => 'Billed Qty Alt', 'searchable' => true, 'sortable' => true, 'align' => 'right', 'showTotal' => true],

            'pur_unit_alt' => ['label' => 'Qty Unit Alt',  'sortable' => true,   'readonly' => true],

            'pur_unit_conv_rate' => ['label' => 'Conversion Rate', 'sortable' => true, 'hidden' => true, 'align' => 'right'],

            'pur_qty_int' => ['label' => 'Internal Qty', 'searchable' => true, 'sortable' => true, 'vRule' => 'required|numeric',  'align' => 'right', 'showTotal' => true],

            'pur_unint_int' => ['label' => 'Internal Unit',  'sortable' => true,  'vRule' => 'required', 'readonly' => true, 'options' => $allunits,],

            'pur_qty_int_alt' => ['label' => 'Internal Qty Alt', 'searchable' => true, 'sortable' => true, 'align' => 'right', 'showTotal' => true],

            'pur_unint_int_alt' => ['label' => 'Internal Unit Alt',  'sortable' => true,   'readonly' => true],

            'pur_rate' => ['label' => 'Billed Rate', 'searchable' => true, 'sortable' => true, 'vRule' => 'required|numeric', 'align' => 'right', 'newlineClass' => 'lg:col-start-1'],

            'pur_rate_int' => ['label' => 'Internal Rate', 'searchable' => true, 'sortable' => true, 'vRule' => 'required|numeric', 'align' => 'right'],

            'pur_amnt' => ['label' => 'Internal Amount', 'searchable' => true, 'sortable' => true, 'readonly' => true, 'align' => 'right', 'showTotal' => true],

            'pur_gst_amnt' => ['label' => 'Gst Amount', 'searchable' => true, 'sortable' => true, 'readonly' => true, 'align' => 'right', 'showTotal' => true],

            'pur_amnt_total' => ['label' => 'Bill Value', 'searchable' => true, 'sortable' => true, 'readonly' => true, 'align' => 'right', 'showTotal' => true],
            'remark' => ['label' => 'Remark', 'searchable' => true, 'sortable' => true],
        ];
        return $formInfo;
    }
    public static function getAllOption()
    {
        $alldatas = Purchase::select('*', 'pur_pr_detail')->groupBy('pur_pr_detail')->get();
        $allOpt = [];
        foreach ($alldatas as $alldata) {
            $allOpt[] = ['id' => $alldata->pur_pr_id, 'label' => $alldata->pur_pr_detail, 'data' => $alldata];
        }
        return $allOpt;
    }
}
