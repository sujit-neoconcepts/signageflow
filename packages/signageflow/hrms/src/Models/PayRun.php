<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class PayRun extends Model
{
    protected $table = 'hrms_pay_runs';

    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'sync_options' => 'array',
        'total_gross' => 'float',
        'total_deductions' => 'float',
        'total_net' => 'float',
        'l1_approved_at' => 'datetime',
        'l2_approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function payGroup()
    {
        return $this->belongsTo(PayGroup::class, 'pay_group_id');
    }

    public function items()
    {
        return $this->hasMany(PayRunItem::class, 'pay_run_id');
    }

    public function glBatches()
    {
        return $this->hasMany(GlBatch::class, 'pay_run_id');
    }
}
