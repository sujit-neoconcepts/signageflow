<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PurchaseInfo extends Model
{
    use HasFactory;

    protected $table = 'purchases_info';

    protected $fillable = [
        'pur_inv',
        'pur_date',
        'received_date',
        'pur_supplier',
        'sum_total',
        'roundoff',
    ];

    public function items()
    {
        return $this->hasMany(Purchase::class, 'purchase_info_id');
    }

    public function scopePurDateStart($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('purchases_info.pur_date', '>=', $start->startOfDay());
    }

    public function scopePurDateEnd($query, $ed)
    {
        $end = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('purchases_info.pur_date', '<=', $end->endOfDay());
    }

    public function scopeReceivedDateStart($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('purchases_info.received_date', '>=', $start->startOfDay());
    }

    public function scopeReceivedDateEnd($query, $ed)
    {
        $end = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('purchases_info.received_date', '<=', $end->endOfDay());
    }
}
