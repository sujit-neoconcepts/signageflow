<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class FwaPolicy extends Model
{
    protected $table = 'hrms_fwa_policies';

    protected $guarded = ['id'];

    protected $casts = [
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
    ];
}
