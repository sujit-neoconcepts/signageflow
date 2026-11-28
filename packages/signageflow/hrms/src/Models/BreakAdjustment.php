<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class BreakAdjustment extends Model
{
    protected $table = 'hrms_break_adjustments';

    protected $guarded = ['id'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function attendanceDay()
    {
        return $this->belongsTo(AttendanceDay::class, 'attendance_day_id');
    }
}
