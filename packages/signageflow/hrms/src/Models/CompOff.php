<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class CompOff extends Model
{
    protected $table = 'hrms_comp_offs';

    protected $guarded = ['id'];

    protected $casts = [
        'worked_date' => 'date',
        'expires_at' => 'date',
        'days_earned' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
