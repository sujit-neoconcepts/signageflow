<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ConsumableInternalName;

class Product extends Model
{
    use HasFactory;
    protected $fillable = ['pr_detail', 'pr_hsn', 'pr_detail_int', 'pr_pur_unit', 'pr_pur_unit_alt', 'pr_int_unit', 'pr_int_unit_alt', 'pr_gst_rate', 'pr_min_unit', 'pr_min_unit_alt', 'pr_group', 'groupinfo'];


    public static function formInfo()
    {
        $allunits = Munit::select('name')->orderBy('name')->get()->pluck('name');
        $consumableNames = ConsumableInternalName::select('id', 'name', 'unitName', 'unitAltName')
            ->orderBy('name')
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'label' => $item->name,
                    'data' => [
                        'unitName' => $item->unitName,
                        'unitAltName' => $item->unitAltName
                    ]
                ];
            })
            ->toArray();

        $formInfo = [
            'subgroup' => ['label' => 'Sub Group', 'searchable' => false, 'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' => [
                'Capex',
                'Consumable Item',
                'Indirect Expense/Purchase',
                'Opex',
                'Plant & Machinery Item',
                'Services Purchase',
                'Services Sale',
                'Stock Item',
                'Tools'
            ], 'vRule' => 'required'],

            'groupinfo' => ['label' => 'Product Group', 'searchable' => false, 'sortable' => true, 'type' => 'select',  'options' => Pgroup::getAllOption(), 'vRule' => 'required', 'canAdd' => true, 'filter' => ['on' => 'subgroup', 'comp' => 'sgroup', 'fetch' => 'id']],

            'pr_detail' => ['label' => 'Name As Per Invoice', 'searchable' => true, 'sortable' => true, 'vRule' => 'required|unique:products,pr_detail'],

            'pr_detail_int' => [
                'label' => 'Internal Name', 
                'searchable' => true, 
                'sortable' => true,
                'type' => 'select',
                'options' => $consumableNames,
                'canAdd' => true,
                'autoFill' => [
                    'pr_int_unit' => 'data.unitName',
                    'pr_int_unit_alt' => 'data.unitAltName'
                ]
            ],

            'pr_hsn' => ['label' => 'HSN Code:', 'searchable' => true, 'sortable' => true, 'vRule' => 'required|numeric', 'align' => 'right'],

            'pr_gst_rate' => ['label' => 'Gst Rate', 'searchable' => true, 'sortable' => true, 'vRule' => 'required|numeric', 'align' => 'right'],

            'pr_pur_unit' => ['label' => 'Billed Unit',  'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' => $allunits, 'vRule' => 'required',],

            'pr_int_unit' => ['label' => 'Internal Unit',  'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' => $allunits, 'vRule' => 'required'],

            'pr_pur_unit_alt' => ['label' => 'Billed Unit Alt',  'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' => $allunits,],

            'pr_int_unit_alt' => ['label' => 'Internal Unit Alt',  'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' => $allunits,],

            'pr_min_unit' => ['label' => 'Conversion Value', 'searchable' => false, 'sortable' => true, 'vRule' => 'required|numeric'],
        ];
        return $formInfo;
    }

    public static function getAllOption()
    {
        $alldatas = Product::leftJoin('consumable_internal_names as cin', 'cin.name', '=', 'products.pr_detail_int')
            ->select('products.*', 'cin.unitPrice as master_unit_price')
            ->get();

        // Efficiently fetch stock and rates in bulk by Internal Name (to match /admin/stocks)
        $purSub = \DB::table('purchases')
            ->select('pur_pr_detail_int')
            ->selectRaw('SUM(CASE WHEN entry_type = 0 THEN pur_qty_int WHEN entry_type = 1 THEN pur_qty_int ELSE 0 END) as total_in')
            ->groupBy('pur_pr_detail_int')
            ->get()
            ->pluck('total_in', 'pur_pr_detail_int');

        $outSub = \DB::table('outwards')
            ->select('out_product')
            ->selectRaw('SUM(out_qty) as total_out')
            ->groupBy('out_product')
            ->get()
            ->pluck('total_out', 'out_product');

        // Last rates by Internal Name
        $lastRates = \DB::table('purchases')
            ->select('pur_rate', 'pur_pr_detail_int')
            ->whereIn('id', function($query) {
                $query->selectRaw('MAX(id)')
                    ->from('purchases')
                    ->where('entry_type', 0)
                    ->groupBy('pur_pr_detail_int');
            })
            ->get()
            ->pluck('pur_rate', 'pur_pr_detail_int');

        $allOpt = [];
        foreach ($alldatas as $alldata) {
            $in = $purSub[$alldata->pr_detail_int] ?? 0;
            $out = $outSub[$alldata->pr_detail_int] ?? 0;
            $balance = $in - $out;

            $alldata->last_rate = $lastRates[$alldata->pr_detail_int] ?? 0;
            $alldata->available_qty = $balance;
            $alldata->unit_rate = $alldata->master_unit_price ?? 0;

            $allOpt[] = [
                'id' => $alldata->id, 
                'label' => $alldata->pr_detail, 
                'data' => $alldata
            ];
        }
        return $allOpt;
    }

    public static function getAllOptionInternal()
    {
        $alldatas = ConsumableInternalName::select('*')->orderBy('name')->get();
        $names = $alldatas->pluck('name')->toArray();

        // Efficiently fetch stock and rates in bulk for internal names
        $purSub = \DB::table('purchases')
            ->whereIn('pur_pr_detail_int', $names)
            ->select('pur_pr_detail_int')
            ->selectRaw('SUM(CASE WHEN entry_type = 0 THEN pur_qty_int WHEN entry_type = 1 THEN pur_qty_int ELSE 0 END) as total_in')
            ->groupBy('pur_pr_detail_int')
            ->get()->pluck('total_in', 'pur_pr_detail_int');

        $outSub = \DB::table('outwards')
            ->whereIn('out_product', $names)
            ->select('out_product')
            ->selectRaw('SUM(out_qty) as total_out')
            ->groupBy('out_product')
            ->get()->pluck('total_out', 'out_product');

        $lastRates = \DB::table('purchases')
            ->whereIn('pur_pr_detail_int', $names)
            ->select('pur_rate', 'pur_pr_detail_int')
            ->whereIn('id', function($query) {
                $query->selectRaw('MAX(id)')
                    ->from('purchases')
                    ->where('entry_type', 0)
                    ->groupBy('pur_pr_detail_int');
            })
            ->get()->pluck('pur_rate', 'pur_pr_detail_int');
            
        $minIds = Product::select('pr_detail_int', \DB::raw('MIN(id) as min_id'))
            ->whereIn('pr_detail_int', $names)
            ->groupBy('pr_detail_int')
            ->get()->pluck('min_id', 'pr_detail_int');

        $allOpt = []; 
        foreach ($alldatas as $alldata) {
            $in = $purSub[$alldata->name] ?? 0;
            $out = $outSub[$alldata->name] ?? 0;
            
            $alldata->available_qty = $in - $out;
            $alldata->last_rate = $lastRates[$alldata->name] ?? 0;
            $alldata->unit_rate = $alldata->unitPrice ?? 0;
            
            $allOpt[] = ['id' => $minIds[$alldata->name] ?? null, 'label' => $alldata->name, 'data' => $alldata];
        }
        return $allOpt;
    }

    public static function getAllOption2()
    {
        // Get the minimum ID for each unique pr_detail_int
        $uniqueIds = Product::selectRaw('MIN(id) as id')
            ->groupBy('pr_detail_int')
            ->pluck('id');
        
        // Fetch full records for those IDs
        $alldatas = Product::whereIn('id', $uniqueIds)
            ->orderBy('pr_detail_int')
            ->get();
            
        $allOpt = []; // [['id' => 0, 'label' => 'Products']];
        foreach ($alldatas as $alldata) {
            $allOpt[] = ['id' => $alldata->id, 'label' => $alldata->pr_detail_int, 'data' => $alldata];
        }
        return $allOpt;
    }
}
