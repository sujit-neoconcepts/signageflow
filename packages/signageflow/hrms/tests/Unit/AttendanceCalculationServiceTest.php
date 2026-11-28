<?php

namespace SignageFlow\Hrms\Tests\Unit;

use SignageFlow\Hrms\Models\AttendancePunch;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\OvertimeApproval;
use SignageFlow\Hrms\Models\Shift;
use SignageFlow\Hrms\Services\AttendanceCalculationService;
use SignageFlow\Hrms\Tests\HrmsTestCase;

class AttendanceCalculationServiceTest extends HrmsTestCase
{
    public function test_calculates_late_worked_minutes_and_overtime(): void
    {
        $shift = Shift::create([
            'code' => 'GS',
            'name' => 'General Shift',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'grace_in_minutes' => 15,
            'grace_out_minutes' => 10,
            'break_minutes' => 60,
            'half_day_minutes' => 240,
            'full_day_minutes' => 480,
            'ot_eligible' => true,
            'ot_min_threshold_minutes' => 30,
            'ot_multiplier' => 1.5,
            'week_off_days' => [0],
        ]);

        $emp = Employee::create([
            'emp_code' => 'EMP002',
            'first_name' => 'Riya',
            'full_name' => 'Riya Sharma',
            'date_of_joining' => '2026-04-01',
            'default_shift_id' => $shift->id,
        ]);

        // Punches on a Monday (2026-06-01): 09:30 -> 19:30 (600m gross - 60m break = 540m worked, 60m OT, 30m late)
        AttendancePunch::create([
            'employee_id' => $emp->id,
            'punched_at' => '2026-06-01 09:30:00',
            'punch_date' => '2026-06-01',
            'direction' => 'in',
            'channel' => 'mobile',
        ]);
        AttendancePunch::create([
            'employee_id' => $emp->id,
            'punched_at' => '2026-06-01 19:30:00',
            'punch_date' => '2026-06-01',
            'direction' => 'out',
            'channel' => 'mobile',
        ]);

        $day = (new AttendanceCalculationService)->recalculateDay($emp, '2026-06-01');

        $this->assertSame('present', $day->status);
        $this->assertTrue($day->is_late);
        $this->assertSame(30, $day->late_minutes);
        $this->assertSame(540, $day->worked_minutes);
        $this->assertSame(60, $day->overtime_minutes);
        $this->assertDatabaseHas('hrms_overtime_approvals', [
            'attendance_day_id' => $day->id,
            'computed_ot_minutes' => 60,
        ]);
        $this->assertSame(1, OvertimeApproval::count());
    }
}
