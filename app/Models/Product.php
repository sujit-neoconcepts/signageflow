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
        $alldatas = Product::all();
        $allOpt = []; // [['id' => 0, 'label' => 'Products']];
        foreach ($alldatas as $alldata) {
            $allOpt[] = ['id' => $alldata->id, 'label' => $alldata->pr_detail, 'data' => $alldata];
        }
        return $allOpt;
    }

    public static function getAllOptionInternal()
    {
        $alldatas = ConsumableInternalName::select('*')->orderBy('name')->get();
        $allOpt = []; 
        
        foreach ($alldatas as $alldata) {
            $minId = Product::select('id')->where('pr_detail_int', '=', $alldata->name)->min('id');
            $allOpt[] = ['id' => $minId, 'label' => $alldata->name, 'data' => $alldata];
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
