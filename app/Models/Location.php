<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public static function formInfo()
    {
        $formInfo = ['name' => ['label' => 'Location', 'vRule' => 'required|unique:locations,name',]];
        return $formInfo;
    }

    public static function getAllOption()
    {
        $allDatas = Location::all();
        $allopts = [['id' => '', 'label' => 'Location']];
        foreach ($allDatas as $allData) {
            $allopts[] = ['id' => $allData->name, 'label' => $allData->name];
        }
        return $allopts;
    }
}
