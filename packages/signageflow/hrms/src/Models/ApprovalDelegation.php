<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalDelegation extends Model
{
    protected $table = 'hrms_approval_delegations';

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function delegator()
    {
        return $this->belongsTo(Employee::class, 'delegator_employee_id');
    }

    public function backup()
    {
        return $this->belongsTo(Employee::class, 'backup_employee_id');
    }
}
