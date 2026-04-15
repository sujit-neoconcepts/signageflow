<?php

namespace App\Models;

use App\Traits\FinancialYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SalesOrder extends Model
{
    use HasFactory, FinancialYearScope;

    const DATE_COLUMN = 'order_date';

    protected $fillable = [
        'order_no',
        'order_prefix',
        'order_sequence',
        'order_fy',
        'order_date',
        'client_id',
        'enquiry_id',
        'product_type',
        'remark',
        'transport_charge',
        'gst_percent',
        'roundoff',
        'items_taxable_total',
        'items_gst_total',
        'total_amount',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function enquiry()
    {
        return $this->belongsTo(\App\Models\Enquiry::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function customItems()
    {
        return $this->hasMany(SalesOrderCustomItem::class);
    }

    public function scopeOrderDateStart($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('order_date', '>=', $start->startOfDay());
    }

    public function scopeOrderDateEnd($query, $ed)
    {
        $end = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('order_date', '<=', $end->endOfDay());
    }
}
