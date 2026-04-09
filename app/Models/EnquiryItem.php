<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnquiryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'enquiry_id',
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

    public function enquiry()
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function costSheet()
    {
        return $this->belongsTo(CostSheet::class);
    }
}
