<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class AttendancePunch extends Model
{
    protected $table = 'hrms_attendance_punches';

    protected $guarded = ['id'];

    protected $casts = [
        'punched_at' => 'datetime',
        'punch_date' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
        'distance_meters' => 'float',
        'is_out_of_geofence' => 'boolean',
        'device_meta' => 'array',
        'raw_payload' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }

    public function kioskDevice()
    {
        return $this->belongsTo(KioskDevice::class, 'kiosk_device_id');
    }
}
