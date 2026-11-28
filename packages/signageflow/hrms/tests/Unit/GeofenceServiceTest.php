<?php

namespace SignageFlow\Hrms\Tests\Unit;

use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\WorkLocation;
use SignageFlow\Hrms\Services\GeofenceService;
use SignageFlow\Hrms\Tests\HrmsTestCase;

class GeofenceServiceTest extends HrmsTestCase
{
    public function test_evaluates_inside_and_outside_geofence_modes(): void
    {
        $loc = WorkLocation::create([
            'code' => 'HQ',
            'name' => 'Head Office',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'radius_meters' => 100,
            'geofence_mode' => 'strict',
            'require_selfie' => true,
        ]);

        $emp = Employee::create([
            'emp_code' => 'EMP001',
            'first_name' => 'Aarav',
            'full_name' => 'Aarav Patel',
            'date_of_joining' => '2026-04-01',
            'work_location_id' => $loc->id,
            'geofence_mode' => 'inherit',
        ]);

        $service = new GeofenceService;

        $inside = $service->evaluate($emp, 19.0761, 72.8778);
        $this->assertFalse($inside['is_out_of_geofence']);
        $this->assertSame('allow', $inside['action']);
        $this->assertTrue($inside['selfie_required']);

        $outsideStrict = $service->evaluate($emp, 19.0900, 72.8777);
        $this->assertTrue($outsideStrict['is_out_of_geofence']);
        $this->assertSame('block', $outsideStrict['action']);

        $emp->update(['geofence_mode' => 'flag']);
        $outsideFlag = $service->evaluate($emp->fresh('workLocation'), 19.0900, 72.8777);
        $this->assertTrue($outsideFlag['is_out_of_geofence']);
        $this->assertSame('flag', $outsideFlag['action']);
    }
}
