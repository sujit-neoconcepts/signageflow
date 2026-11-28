<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'hrms_categories';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
