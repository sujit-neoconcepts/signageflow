<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class BankTemplate extends Model
{
    protected $table = 'hrms_bank_templates';

    protected $guarded = ['id'];

    protected $casts = [
        'columns_mapping' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
