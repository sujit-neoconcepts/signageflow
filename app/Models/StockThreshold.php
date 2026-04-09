<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockThreshold extends Model
{
    protected $fillable = ['pr_detail_int', 'threshold_qty'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'pr_detail_int', 'pr_detail_int');
    }
}
