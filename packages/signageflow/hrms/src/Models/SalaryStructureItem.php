<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryStructureItem extends Model
{
    protected $table = 'hrms_salary_structure_items';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'float',
    ];

    public function structure()
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function component()
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
