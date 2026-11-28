<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $table = 'hrms_sections';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
