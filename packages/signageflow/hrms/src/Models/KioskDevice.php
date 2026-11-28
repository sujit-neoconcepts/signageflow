<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class KioskDevice extends Model
{
    protected $table = 'hrms_kiosk_devices';

    protected $guarded = ['id'];

    protected $hidden = ['device_token_hash'];

    protected $casts = [
        'require_snapshot' => 'boolean',
        'allow_pin' => 'boolean',
        'allow_qr' => 'boolean',
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }
}
