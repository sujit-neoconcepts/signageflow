<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class GlBatch extends Model
{
    protected $table = 'hrms_gl_batches';

    protected $guarded = ['id'];

    protected $casts = [
        'voucher_date' => 'date',
        'entries_json' => 'array',
        'total_debit' => 'float',
        'total_credit' => 'float',
    ];

    public function payRun()
    {
        return $this->belongsTo(PayRun::class, 'pay_run_id');
    }
}
