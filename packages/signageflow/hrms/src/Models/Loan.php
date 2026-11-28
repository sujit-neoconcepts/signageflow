<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $table = 'hrms_loans';

    protected $guarded = ['id'];

    protected $casts = [
        'principal_amount' => 'float',
        'interest_rate' => 'float',
        'emi_amount' => 'float',
        'outstanding_amount' => 'float',
        'disbursed_date' => 'date',
        'start_deduction_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function installments()
    {
        return $this->hasMany(LoanInstallment::class, 'loan_id')->orderBy('installment_no');
    }
}
