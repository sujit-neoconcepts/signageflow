<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'hrms_departments';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function sections()
    {
        return $this->hasMany(Section::class, 'department_id');
    }
}
