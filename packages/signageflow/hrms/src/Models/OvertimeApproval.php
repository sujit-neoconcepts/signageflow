<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeApproval extends Model
{
    protected $table = 'hrms_overtime_approvals';

    protected $guarded = ['id'];

    protected $casts = [
        'attendance_date' => 'date',
        'acted_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function attendanceDay()
    {
        return $this->belongsTo(AttendanceDay::class, 'attendance_day_id');
    }
}
