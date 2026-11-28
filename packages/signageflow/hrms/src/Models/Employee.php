<?php

namespace SignageFlow\Hrms\Models;

use Illuminate\Database\Eloquent\Model;
use SignageFlow\Hrms\Models\Concerns\AppliesOrgFilters;

class Employee extends Model
{
    use AppliesOrgFilters;

    protected $table = 'hrms_employees';

    protected $guarded = ['id'];

    protected $hidden = ['kiosk_pin_hash'];

    protected $casts = [
        'dob' => 'date',
        'date_of_joining' => 'date',
        'date_of_exit' => 'date',
        'selfie_required' => 'boolean',
        'mobile_attendance_enabled' => 'boolean',
        'epf_enabled' => 'boolean',
        'esic_enabled' => 'boolean',
        'pt_enabled' => 'boolean',
        'lwf_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(config('hrms.models.user'), 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function extraClassification()
    {
        return $this->belongsTo(ExtraClassification::class, 'extra_classification_id');
    }

    public function l1Approver()
    {
        return $this->belongsTo(self::class, 'l1_approver_id');
    }

    public function l2Approver()
    {
        return $this->belongsTo(self::class, 'l2_approver_id');
    }

    public function backupApprover()
    {
        return $this->belongsTo(self::class, 'backup_approver_id');
    }

    public function defaultShift()
    {
        return $this->belongsTo(Shift::class, 'default_shift_id');
    }

    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }

    public function assignedWorkLocations()
    {
        return $this->belongsToMany(WorkLocation::class, 'hrms_employee_work_locations', 'employee_id', 'work_location_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('employment_status', ['active', 'probation', 'notice']);
    }
}
