<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class PayGroup extends Model
{
    protected $table = 'hrms_pay_groups';

    protected $guarded = ['id'];

    protected $casts = [
        'tally_ledger_mapping' => 'array',
        'is_active' => 'boolean',
    ];

    public function l1Approver()
    {
        return $this->belongsTo(config('hrms.models.user'), 'l1_approver_user_id');
    }

    public function l2Approver()
    {
        return $this->belongsTo(config('hrms.models.user'), 'l2_approver_user_id');
    }

    public function defaultBankTemplate()
    {
        return $this->belongsTo(BankTemplate::class, 'default_bank_template_id');
    }
}
