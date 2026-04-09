<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pgroup extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'sgroup'];

    public static function formInfo()
    {
        $formInfo = [
            'name' => ['label' => 'Name', 'vRule' => 'required|unique:pgroups,name',],
            'sgroup' => ['label' => 'Sub Group', 'vRule' => 'required', 'searchable' => false, 'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' => [
  'Capex',
  'Consumable Item',
  'Indirect Expense/Purchase',
  'Opex',
  'Plant & Machinery Item',
  'Services Purchase',
  'Services Sale',
  'Stock Item',
  'Tools'
], 'vRule' => 'required',],
        ];
        return $formInfo;
    }

    public static function getAllOption()
    {
        $allDatas = Pgroup::all()->sortBy("name");
        $allopts = [];
        foreach ($allDatas as $allData) {
            $allopts[] = ['id' => $allData->id, 'label' => $allData->name . " (" . $allData->sgroup . ")", 'sgroup' => $allData->sgroup];
        }
        return $allopts;
    }

    public static function getStockOption()
    {
        $allDatas = Pgroup::where('sgroup', 'Stock Item')->get()->sortBy("name");
        $allopts = [];
        foreach ($allDatas as $allData) {
            $allopts[] = ['id' => $allData->id, 'label' => $allData->name . " (" . $allData->sgroup . ")"];
        }
        return $allopts;
    }
}
