<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveTransaction extends Model
{
    protected $table = 'hrms_leave_transactions';

    protected $guarded = ['id'];

    protected $casts = [
        'days' => 'float',
        'balance_after' => 'float',
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
