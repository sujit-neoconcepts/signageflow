<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class LoanInstallment extends Model
{
    protected $table = 'hrms_loan_installments';

    protected $guarded = ['id'];

    protected $casts = [
        'due_date' => 'date',
        'emi_amount' => 'float',
        'principal_component' => 'float',
        'interest_component' => 'float',
        'paid_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }
}
