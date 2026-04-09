<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenStockBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_name',
        'location',
        'incharge',
        'open_stock_unit',
        'qty',
    ];
}
