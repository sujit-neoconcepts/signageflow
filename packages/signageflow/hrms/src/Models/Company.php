<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'hrms_companies';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function departments()
    {
        return $this->hasMany(Department::class, 'company_id');
    }
}
