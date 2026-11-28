<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class PayRunItem extends Model
{
    protected $table = 'hrms_pay_run_items';

    protected $guarded = ['id'];

    protected $casts = [
        'is_initialized' => 'boolean',
        'payable_days' => 'float',
        'lop_days' => 'float',
        'ot_hours' => 'float',
        'attendance_summary' => 'array',
        'earnings_json' => 'array',
        'deductions_json' => 'array',
        'employer_contributions_json' => 'array',
        'gross_earnings' => 'float',
        'epf_employee' => 'float',
        'epf_employer' => 'float',
        'esic_employee' => 'float',
        'esic_employer' => 'float',
        'pt_amount' => 'float',
        'lwf_amount' => 'float',
        'tds_amount' => 'float',
        'loan_emi_amount' => 'float',
        'claim_reimbursement_amount' => 'float',
        'attendance_allowance_amount' => 'float',
        'arrear_amount' => 'float',
        'fnf_settlement_amount' => 'float',
        'total_deductions' => 'float',
        'net_wages' => 'float',
        'is_published' => 'boolean',
    ];

    public function payRun()
    {
        return $this->belongsTo(PayRun::class, 'pay_run_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
