<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRequest extends Model
{
    protected $table = 'hrms_attendance_requests';

    protected $guarded = ['id'];

    protected $casts = [
        'attendance_date' => 'date',
        'requested_in_at' => 'datetime',
        'requested_out_at' => 'datetime',
        'l1_acted_at' => 'datetime',
        'l2_acted_at' => 'datetime',
        'acted_via_backup' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function currentApprover()
    {
        return $this->belongsTo(Employee::class, 'current_approver_id');
    }
}
