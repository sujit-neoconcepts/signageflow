<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends Model
{
    protected $table = 'hrms_salary_components';

    protected $guarded = ['id'];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_pf_wage' => 'boolean',
        'is_esic_wage' => 'boolean',
        'is_pt_wage' => 'boolean',
        'is_lwf_wage' => 'boolean',
        'is_active' => 'boolean',
    ];
}
