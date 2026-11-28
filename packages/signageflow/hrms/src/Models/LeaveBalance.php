<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $table = 'hrms_leave_balances';

    protected $guarded = ['id'];

    protected $casts = [
        'opening_balance' => 'float',
        'accrued' => 'float',
        'credited_adjustment' => 'float',
        'taken' => 'float',
        'pending' => 'float',
        'lapsed' => 'float',
        'encashed' => 'float',
        'closing_balance' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
