<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionRequest extends Model
{
    protected $table = 'hrms_permission_requests';

    protected $guarded = ['id'];

    protected $casts = [
        'permission_date' => 'date',
        'acted_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
