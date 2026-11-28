<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveYearEndRun extends Model
{
    protected $table = 'hrms_leave_year_end_runs';

    protected $guarded = ['id'];

    protected $casts = [
        'total_carried_forward' => 'float',
        'total_lapsed' => 'float',
    ];
}
