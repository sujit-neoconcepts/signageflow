<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceAllowanceEntry extends Model
{
    protected $table = 'hrms_attendance_allowance_entries';

    protected $guarded = ['id'];

    protected $casts = [
        'computed_amount' => 'decimal:2',
        'is_eligible' => 'boolean',
    ];

    public function allowance()
    {
        return $this->belongsTo(AttendanceAllowance::class, 'attendance_allowance_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
