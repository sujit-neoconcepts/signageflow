<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expuser extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public static function formInfo()
    {
        $formInfo = ['name' => ['label' => 'Name', 'vRule' => 'required|unique:expusers,name',]];
        return $formInfo;
    }

    public static function getAllOption()
    {
        $allDatas = Expuser::all();
        $allopts = [];
        foreach ($allDatas as $allData) {
            $allopts[] = ['id' => $allData->name, 'label' => $allData->name];
        }
        return $allopts;
    }
}
