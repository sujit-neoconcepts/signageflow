<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'cost_sheet_id',
        'item_name',
        'qty_mode',
        'length',
        'width',
        'pieces',
        'qty',
        'rate',
        'line_total',
        'taxable_amount',
        'gst_percent',
        'gst_amount',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function costSheet()
    {
        return $this->belongsTo(CostSheet::class);
    }
}
