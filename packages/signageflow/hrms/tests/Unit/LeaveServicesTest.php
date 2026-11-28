<?php

namespace SignageFlow\Hrms\Tests\Unit;

use Illuminate\Validation\ValidationException;
use SignageFlow\Hrms\Models\ApprovalDelegation;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\LeaveRequest;
use SignageFlow\Hrms\Models\LeaveType;
use SignageFlow\Hrms\Services\ApprovalRoutingService;
use SignageFlow\Hrms\Services\AttendanceCalculationService;
use SignageFlow\Hrms\Services\FinancialYearHelper;
use SignageFlow\Hrms\Services\LeaveAttendanceSyncService;
use SignageFlow\Hrms\Services\LeaveBalanceService;
use SignageFlow\Hrms\Tests\HrmsTestCase;

class LeaveServicesTest extends HrmsTestCase
{
    public function test_fy_bounds_and_leave_balance_year_end_and_sync(): void
    {
        $this->assertSame('2025-2026', FinancialYearHelper::forDate('2026-03-31'));
        $this->assertSame('2026-2027', FinancialYearHelper::forDate('2026-04-01'));

        $backup = Employee::create([
            'emp_code' => 'MGR_BK',
            'first_name' => 'Backup',
            'full_name' => 'Backup Officer',
            'date_of_joining' => '2025-04-01',
        ]);
        $l1 = Employee::create([
            'emp_code' => 'MGR01',
            'first_name' => 'Manager',
            'full_name' => 'Manager One',
            'date_of_joining' => '2025-04-01',
            'backup_approver_id' => $backup->id,
        ]);
        $emp = Employee::create([
            'emp_code' => 'EMP003',
            'first_name' => 'Karan',
            'full_name' => 'Karan Verma',
            'date_of_joining' => '2026-04-01',
            'l1_approver_id' => $l1->id,
        ]);

        ApprovalDelegation::create([
            'delegator_employee_id' => $l1->id,
            'backup_employee_id' => $backup->id,
            'module' => 'leave',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
            'is_active' => true,
        ]);

        $resolved = (new ApprovalRoutingService)->resolveApprover($emp, 1, 'leave', \Carbon\Carbon::parse('2026-06-05'));
        $this->assertTrue($resolved['via_backup']);
        $this->assertSame($backup->id, $resolved['approver']->id);

        $el = LeaveType::create([
            'code' => 'EL',
            'name' => 'Earned Leave',
            'is_paid' => true,
            'annual_quota' => 15,
            'max_carry_forward' => 5,
            'accrual_frequency' => 'monthly',
        ]);

        $balService = new LeaveBalanceService;
        $balService->mutateBalance($emp, $el, '2026-2027', 'opening', 8);
        $bal = $balService->mutateBalance($emp, $el, '2026-2027', 'debit', 2);
        $this->assertSame(6.0, $bal->closing_balance);

        // Year-end: 6 remaining, max CF 5 -> 5 CF to 2027-2028, 1 lapsed
        $run = $balService->runYearEnd('2026-2027');
        $this->assertSame(5.0, $run->total_carried_forward);
        $this->assertSame(1.0, $run->total_lapsed);

        // Sync approved leave overrides absent -> on_leave
        $req = LeaveRequest::create([
            'employee_id' => $emp->id,
            'leave_type_id' => $el->id,
            'financial_year' => '2026-2027',
            'start_date' => '2026-06-02',
            'end_date' => '2026-06-02',
            'day_mode' => 'full',
            'total_days' => 1,
            'reason' => 'Family function',
            'approval_status' => 'approved',
        ]);
        (new LeaveAttendanceSyncService(new AttendanceCalculationService))->syncApprovedLeave($req->load(['employee', 'leaveType']));

        $attDay = \SignageFlow\Hrms\Models\AttendanceDay::where('employee_id', $emp->id)
            ->whereDate('attendance_date', '2026-06-02')
            ->first();
        $this->assertNotNull($attDay);
        $this->assertSame('on_leave', $attDay->status);
        $this->assertSame('EL', $attDay->leave_code);

        // Negative balance guard
        $this->expectException(ValidationException::class);
        $balService->mutateBalance($emp, $el, '2027-2028', 'debit', 10);
    }
}
