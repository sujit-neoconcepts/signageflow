<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDay extends Model
{
    protected $table = 'hrms_attendance_days';

    protected $guarded = ['id'];

    protected $casts = [
        'attendance_date' => 'date',
        'first_in_at' => 'datetime',
        'last_out_at' => 'datetime',
        'is_late' => 'boolean',
        'is_early_out' => 'boolean',
        'is_geofence_flagged' => 'boolean',
        'is_locked_by_payroll' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
