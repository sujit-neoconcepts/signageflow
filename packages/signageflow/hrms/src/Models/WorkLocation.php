<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLocation extends Model
{
    protected $table = 'hrms_work_locations';

    protected $guarded = ['id'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_meters' => 'integer',
        'require_selfie' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function hostLocation()
    {
        return $this->belongsTo(config('hrms.models.location'), 'host_location_id');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'hrms_employee_work_locations', 'work_location_id', 'employee_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
