<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceAllowance extends Model
{
    protected $table = 'hrms_attendance_allowances';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
