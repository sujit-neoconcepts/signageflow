<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $table = 'hrms_leave_types';

    protected $guarded = ['id'];

    protected $casts = [
        'is_paid' => 'boolean',
        'allow_half_day' => 'boolean',
        'requires_attachment' => 'boolean',
        'annual_quota' => 'float',
        'max_carry_forward' => 'float',
        'allow_encashment' => 'boolean',
        'applicable_categories' => 'array',
        'is_statutory' => 'boolean',
        'is_active' => 'boolean',
    ];
}
