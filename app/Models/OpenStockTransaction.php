<?php

namespace App\Models;

use App\Traits\FinancialYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OpenStockTransaction extends Model
{
    use HasFactory, FinancialYearScope;

    const DATE_COLUMN = 'txn_date';

    protected $fillable = [
        'txn_date',
        'transaction_type',
        'internal_name',
        'location',
        'incharge',
        'open_stock_unit',
        'qty',
        'base_unit_price',
        'margin_percent',
        'effective_unit_price',
        'line_amount',
        'source_type',
        'source_id',
        'source_item_id',
        'remark',
    ];

    public function scopeTxnDateStart($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('txn_date', '>=', $start->startOfDay());
    }

    public function scopeTxnDateEnd($query, $ed)
    {
        $end = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('txn_date', '<=', $end->endOfDay());
    }
}
