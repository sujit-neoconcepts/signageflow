<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class FwaRequest extends Model
{
    protected $table = 'hrms_fwa_requests';

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'acted_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function policy()
    {
        return $this->belongsTo(FwaPolicy::class, 'fwa_policy_id');
    }
}
