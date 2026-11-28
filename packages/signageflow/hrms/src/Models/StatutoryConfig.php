<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class StatutoryConfig extends Model
{
    protected $table = 'hrms_statutory_configs';

    protected $guarded = ['id'];

    protected $casts = [
        'epf_employee_rate' => 'float',
        'epf_employer_rate' => 'float',
        'epf_wage_ceiling' => 'float',
        'epf_admin_rate' => 'float',
        'esic_employee_rate' => 'float',
        'esic_employer_rate' => 'float',
        'esic_wage_ceiling' => 'float',
        'pt_state_slabs' => 'array',
        'lwf_rules' => 'array',
        'is_active' => 'boolean',
    ];
}
