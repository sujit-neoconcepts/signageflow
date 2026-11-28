<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryStructure extends Model
{
    protected $table = 'hrms_salary_structures';

    protected $guarded = ['id'];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'monthly_ctc' => 'float',
        'monthly_gross' => 'float',
        'basic_salary' => 'float',
        'is_active' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function payGroup()
    {
        return $this->belongsTo(PayGroup::class, 'pay_group_id');
    }

    public function items()
    {
        return $this->hasMany(SalaryStructureItem::class, 'salary_structure_id');
    }
}
