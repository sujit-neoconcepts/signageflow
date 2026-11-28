<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $table = 'hrms_shifts';

    protected $guarded = ['id'];

    protected $casts = [
        'is_overnight' => 'boolean',
        'ot_eligible' => 'boolean',
        'ot_multiplier' => 'decimal:2',
        'week_off_days' => 'array',
        'is_active' => 'boolean',
    ];
}
