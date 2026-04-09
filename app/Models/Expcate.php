<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expcate extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public static function formInfo()
    {
        $formInfo = ['name' => ['label' => 'Name', 'vRule' => 'required|unique:expcates,name',]];
        return $formInfo;
    }

    public static function getAllOption()
    {
        $allDatas = Expcate::all();
        $allopts = [['id' => '', 'label' => 'Exp. Categories']];
        foreach ($allDatas as $allData) {
            $allopts[] = ['id' => $allData->name, 'label' => $allData->name];
        }
        return $allopts;
    }
}
