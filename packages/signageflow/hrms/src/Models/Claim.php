<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    protected $table = 'hrms_claims';

    protected $guarded = ['id'];

    protected $casts = [
        'claim_date' => 'date',
        'amount' => 'float',
        'l1_acted_at' => 'datetime',
        'l2_acted_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function hostExpense()
    {
        return $this->belongsTo(config('hrms.models.expense'), 'host_expense_id');
    }

    public function currentApprover()
    {
        return $this->belongsTo(Employee::class, 'current_approver_id');
    }
}
