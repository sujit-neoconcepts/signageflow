<?php

namespace App\Models;

use App\Traits\FinancialYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Enquiry extends Model
{
    use HasFactory, FinancialYearScope;

    const DATE_COLUMN = 'enquiry_date';

    protected $fillable = [
        'enquiry_no',
        'enquiry_prefix',
        'enquiry_sequence',
        'enquiry_fy',
        'enquiry_date',
        'client_id',
        'product_type',
        'remark',
        'transport_charge',
        'gst_percent',
        'items_taxable_total',
        'items_gst_total',
        'total_amount',
        'status',
    ];

    const STATUS_OPEN = 'open';
    const STATUS_PUSHED = 'pushed_to_sales';

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items()
    {
        return $this->hasMany(EnquiryItem::class);
    }

    public function customItems()
    {
        return $this->hasMany(EnquiryCustomItem::class);
    }

    public function files()
    {
        return $this->hasMany(EnquiryFile::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(\App\Models\SalesOrder::class);
    }

    public function scopeOrderDateStart($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('enquiry_date', '>=', $start->startOfDay());
    }

    public function scopeOrderDateEnd($query, $ed)
    {
        $end = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('enquiry_date', '<=', $end->endOfDay());
    }
}
