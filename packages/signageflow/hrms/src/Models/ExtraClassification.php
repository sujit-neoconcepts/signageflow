<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraClassification extends Model
{
    protected $table = 'hrms_extra_classifications';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
