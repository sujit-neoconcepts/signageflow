<?php

namespace App\Models;

use App\Models\User;
use App\Models\Expcate;
use App\Models\Expuser;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Traits\FinancialYearScope;

class Expense extends Model
{
    use HasFactory, FinancialYearScope;
    const DATE_COLUMN = 'exp_date';
    protected $fillable = ['exp_date', 'amount', 'doneby', 'exp_cate', 'details', 'job_details', 'job_no', 'incharge', 'amt_type'];

    public static function formInfo()
    {
        $formInfo = [
            'exp_date' => ['label' => 'Date', 'sortable' => true, 'vRule' => 'required', 'type' => 'datepicker'],
            'incharge' => ['label' => 'Users',  'sortable' => true, 'vRule' => 'required', 'type' => 'select', 'optionType' => 'array', 'options' =>  User::select('name')->orderBy('name')->get()->pluck('name'), 'default' => Auth::user()->name],
            'job_details' => ['label' => 'Location', 'searchable' => true, 'sortable' => true, 'vRule' => 'required'],
        ];
        return $formInfo;
    }
    public static function formInfoMulti()
    {
        $formInfo = [
            'amount' => ['label' => 'Amount', 'type' => 'decimal', 'sortable' => true, 'vRule' => 'required|numeric', 'align' => 'right', 'showTotal' => true],

            'amt_type' => ['label' => 'Type',  'vRule' => 'required', 'type' => 'select', 'optionType' => 'array', 'options' =>  ['Expense', 'Deposit'],],

            'doneby' => ['label' => 'Done By',   'type' => 'multiselect', 'optionType' => 'array', 'options' =>  Expuser::getAllOption(), 'colspan' => 2],

            'exp_cate' => ['label' => 'Exp Cate', 'sortable' => true, 'vRule' => 'required', 'type' => 'select', 'optionType' => 'array', 'options' =>  Expcate::select('name')->orderBy('name')->get()->pluck('name'),],
            'details' => ['label' => 'Exp Detail', 'searchable' => true, 'sortable' => true,],
            'job_no' => ['label' => 'Comments', 'searchable' => true, 'sortable' => true,],
        ];
        return $formInfo;
    }
    public function scopeExpDateStart($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('exp_date', '>=', $start->startOfDay());
    }

    public function scopeExpDateEnd($query, $ed)
    {
        $end   = ($ed instanceof Carbon) ? $ed : Carbon::parse($ed);
        return $query->where('exp_date', '<=', $end->endOfDay());
    }

    public function scopeExpDatebefore($query, $sd)
    {
        $start = ($sd instanceof Carbon) ? $sd : Carbon::parse($sd);
        return $query->where('exp_date', '<', $start->startOfDay());
    }
}
