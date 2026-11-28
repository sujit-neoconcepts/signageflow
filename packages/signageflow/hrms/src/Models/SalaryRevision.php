<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryRevision extends Model
{
    protected $table = 'hrms_salary_revisions';

    protected $guarded = ['id'];

    protected $casts = [
        'previous_ctc' => 'float',
        'new_ctc' => 'float',
        'increment_percent' => 'float',
        'effective_from' => 'date',
        'arrear_applicable' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
