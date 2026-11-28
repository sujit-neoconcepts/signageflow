<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $table = 'hrms_designations';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'level' => 'integer'];
}
