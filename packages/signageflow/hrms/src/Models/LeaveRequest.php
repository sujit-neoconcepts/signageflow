<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $table = 'hrms_leave_requests';

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'float',
        'l1_acted_at' => 'datetime',
        'l2_acted_at' => 'datetime',
        'acted_via_backup' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function currentApprover()
    {
        return $this->belongsTo(Employee::class, 'current_approver_id');
    }

    public function l1Approver()
    {
        return $this->belongsTo(Employee::class, 'l1_approver_id');
    }

    public function l2Approver()
    {
        return $this->belongsTo(Employee::class, 'l2_approver_id');
    }
}
