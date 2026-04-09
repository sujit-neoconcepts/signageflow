<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    protected $fillable = ['sp_name', 'sp_addr', 'sp_phn', 'sp_email', 'sp_gst'];

    public static function formInfo()
    {
        $formInfo = [
            'sp_name' => ['label' => 'Supplier Name', 'vRule' => 'required|unique:suppliers,sp_name'],
            'sp_addr' => ['label' => 'Address', 'vRule' => 'required'],
            'sp_phn' => ['label' => 'Phone'],
            'sp_email' => ['label' => 'Email', 'vRule' => 'nullable|email'],
            'sp_gst' => ['label' => 'Gst'],
        ];

        return $formInfo;
    }

    public static function getAllOption()
    {
        $allSuppliers = Supplier::all();

        $allData = [];
        foreach ($allSuppliers as $allSupplier) {
            $allData[] = ['id' => $allSupplier->id, 'label' => $allSupplier->sp_name];
        }
        //print_r($allData);
        //exit;
        return $allData;
    }
}
