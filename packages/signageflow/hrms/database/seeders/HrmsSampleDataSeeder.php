<?php

namespace SignageFlow\Hrms\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SignageFlow\Hrms\Models\ApprovalDelegation;
use SignageFlow\Hrms\Models\AttendanceDay;
use SignageFlow\Hrms\Models\AttendancePunch;
use SignageFlow\Hrms\Models\AttendanceRequest;
use SignageFlow\Hrms\Models\Category;
use SignageFlow\Hrms\Models\Claim;
use SignageFlow\Hrms\Models\Company;
use SignageFlow\Hrms\Models\CompOff;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Designation;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\ExtraClassification;
use SignageFlow\Hrms\Models\FwaPolicy;
use SignageFlow\Hrms\Models\FwaRequest;
use SignageFlow\Hrms\Models\Holiday;
use SignageFlow\Hrms\Models\KioskDevice;
use SignageFlow\Hrms\Models\LeaveRequest;
use SignageFlow\Hrms\Models\LeaveType;
use SignageFlow\Hrms\Models\Loan;
use SignageFlow\Hrms\Models\LoanInstallment;
use SignageFlow\Hrms\Models\OvertimeApproval;
use SignageFlow\Hrms\Models\PayGroup;
use SignageFlow\Hrms\Models\SalaryComponent;
use SignageFlow\Hrms\Models\SalaryStructure;
use SignageFlow\Hrms\Models\SalaryStructureItem;
use SignageFlow\Hrms\Models\Section;
use SignageFlow\Hrms\Models\Shift;
use SignageFlow\Hrms\Models\WorkLocation;
use SignageFlow\Hrms\Services\AttendanceCalculationService;
use SignageFlow\Hrms\Services\FinancialYearHelper;
use SignageFlow\Hrms\Services\LeaveBalanceService;

class HrmsSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            HrmsPermissionSeeder::class,
            HrmsDefaultPresetsSeeder::class,
        ]);

        // 1. Org Hierarchy
        $company = Company::firstOrCreate(
            ['code' => 'TIWARI'],
            ['name' => 'Tiwari Industries Pvt. Ltd.', 'legal_name' => 'Tiwari Industries Private Limited', 'gstin' => '27AAACT1234F1Z5']
        );

        $depts = [
            'FAB' => Department::firstOrCreate(['code' => 'FAB'], ['name' => 'Fabrication', 'company_id' => $company->id]),
            'INST' => Department::firstOrCreate(['code' => 'INST'], ['name' => 'Installation & Field', 'company_id' => $company->id]),
            'DES' => Department::firstOrCreate(['code' => 'DES'], ['name' => 'Design & Graphics', 'company_id' => $company->id]),
            'OPS' => Department::firstOrCreate(['code' => 'OPS'], ['name' => 'Operations & HR', 'company_id' => $company->id]),
        ];

        $sections = [
            'CNC' => Section::firstOrCreate(['code' => 'CNC'], ['name' => 'CNC & Laser', 'department_id' => $depts['FAB']->id]),
            'SITE' => Section::firstOrCreate(['code' => 'SITE'], ['name' => 'Site Crew', 'department_id' => $depts['INST']->id]),
            'GFX' => Section::firstOrCreate(['code' => 'GFX'], ['name' => 'Studio Graphics', 'department_id' => $depts['DES']->id]),
        ];

        $desigs = [
            'DIR' => Designation::firstOrCreate(['code' => 'DIR'], ['name' => 'Director', 'level' => 1]),
            'MGR' => Designation::firstOrCreate(['code' => 'MGR'], ['name' => 'Operations Manager', 'level' => 2]),
            'SUP' => Designation::firstOrCreate(['code' => 'SUP'], ['name' => 'Site Supervisor', 'level' => 3]),
            'ENG' => Designation::firstOrCreate(['code' => 'ENG'], ['name' => 'Field Engineer', 'level' => 4]),
            'TECH' => Designation::firstOrCreate(['code' => 'TECH'], ['name' => 'Fabrication Technician', 'level' => 5]),
        ];

        $cats = [
            'STAFF' => Category::firstOrCreate(['code' => 'STAFF'], ['name' => 'Permanent Staff']),
            'FIELD' => Category::firstOrCreate(['code' => 'FIELD'], ['name' => 'Field Crew']),
            'CONT' => Category::firstOrCreate(['code' => 'CONT'], ['name' => 'Contract']),
        ];

        $extra = ExtraClassification::firstOrCreate(['code' => 'WORKSHOP'], ['name' => 'Main Workshop']);

        // 2. Shifts, Work Locations & Kiosk
        $shiftGs = Shift::where('code', 'GS')->firstOrFail();
        $shift03 = Shift::firstOrCreate(
            ['code' => '03'],
            ['name' => 'Morning Shift (10:00 - 18:00)', 'start_time' => '10:00:00', 'end_time' => '18:00:00', 'grace_in_minutes' => 15, 'grace_out_minutes' => 10, 'break_minutes' => 45, 'half_day_minutes' => 240, 'full_day_minutes' => 435, 'week_off_days' => [0]]
        );
        $shift05 = Shift::firstOrCreate(
            ['code' => '05'],
            ['name' => 'Extended Shift (09:00 - 19:00)', 'start_time' => '09:00:00', 'end_time' => '19:00:00', 'grace_in_minutes' => 15, 'grace_out_minutes' => 10, 'break_minutes' => 60, 'half_day_minutes' => 240, 'full_day_minutes' => 540, 'week_off_days' => [0]]
        );

        $locHq = WorkLocation::firstOrCreate(
            ['code' => 'HQ'],
            ['name' => 'Mumbai HQ Workshop', 'latitude' => 19.0760, 'longitude' => 72.8777, 'radius_meters' => 200, 'geofence_mode' => 'flag', 'require_selfie' => false, 'address' => 'Andheri East, Mumbai']
        );
        $locSite = WorkLocation::firstOrCreate(
            ['code' => 'SITE1'],
            ['name' => 'BKC Client Site', 'latitude' => 19.0653, 'longitude' => 72.8685, 'radius_meters' => 150, 'geofence_mode' => 'strict', 'require_selfie' => true, 'address' => 'BKC, Mumbai']
        );

        KioskDevice::firstOrCreate(
            ['code' => 'KIOSK-HQ-01'],
            ['name' => 'Workshop Gate Tablet', 'work_location_id' => $locHq->id, 'device_token_hash' => Hash::make('signageflow-kiosk-demo'), 'allow_pin' => true, 'allow_qr' => true]
        );

        // 3. Employees (linking host users if present + demo kiosk staff)
        $userClass = config('hrms.models.user');
        $adminUser = $userClass::orderBy('id')->first();
        $managerUser = $userClass::where('email', 'manager@manager.com')->first();
        $exec1User = $userClass::where('email', 'executive@executive.com')->first();
        $exec2User = $userClass::where('email', 'executive2@executive.com')->first();

        $empAdmin = $this->upsertEmployee('EMP000', [
            'user_id' => $adminUser?->id,
            'first_name' => $adminUser?->name ?? 'Sujit',
            'last_name' => 'Tiwari',
            'gender' => 'male',
            'date_of_joining' => '2022-04-01',
            'company_id' => $company->id,
            'department_id' => $depts['OPS']->id,
            'designation_id' => $desigs['DIR']->id,
            'category_id' => $cats['STAFF']->id,
            'default_shift_id' => $shiftGs->id,
            'work_location_id' => $locHq->id,
            'official_email' => $adminUser?->email ?? 'admin@signageflow.local',
        ]);

        $empMgr = $this->upsertEmployee('EMP001', [
            'user_id' => ($managerUser && $managerUser->id !== $adminUser?->id) ? $managerUser->id : null,
            'first_name' => 'Rajesh',
            'last_name' => 'Kulkarni',
            'gender' => 'male',
            'date_of_joining' => '2023-04-01',
            'company_id' => $company->id,
            'department_id' => $depts['OPS']->id,
            'designation_id' => $desigs['MGR']->id,
            'category_id' => $cats['STAFF']->id,
            'l1_approver_id' => $empAdmin->id,
            'default_shift_id' => $shiftGs->id,
            'work_location_id' => $locHq->id,
            'official_email' => 'manager@manager.com',
        ]);

        $empSup = $this->upsertEmployee('EMP002', [
            'user_id' => $exec2User?->id,
            'first_name' => 'Priya',
            'last_name' => 'Nair',
            'gender' => 'female',
            'date_of_joining' => '2023-07-15',
            'company_id' => $company->id,
            'department_id' => $depts['DES']->id,
            'section_id' => $sections['GFX']->id,
            'designation_id' => $desigs['SUP']->id,
            'category_id' => $cats['STAFF']->id,
            'l1_approver_id' => $empMgr->id,
            'l2_approver_id' => $empAdmin->id,
            'default_shift_id' => $shift03->id,
            'work_location_id' => $locHq->id,
            'official_email' => 'executive2@executive.com',
        ]);

        $empExec = $this->upsertEmployee('EMP003', [
            'user_id' => $exec1User?->id,
            'first_name' => 'Amit',
            'last_name' => 'Verma',
            'gender' => 'male',
            'date_of_joining' => '2024-04-01',
            'company_id' => $company->id,
            'department_id' => $depts['INST']->id,
            'section_id' => $sections['SITE']->id,
            'designation_id' => $desigs['ENG']->id,
            'category_id' => $cats['FIELD']->id,
            'l1_approver_id' => $empMgr->id,
            'l2_approver_id' => $empAdmin->id,
            'backup_approver_id' => $empSup->id,
            'default_shift_id' => $shift05->id,
            'work_location_id' => $locSite->id,
            'official_email' => 'executive@executive.com',
        ]);

        $extraStaffSpecs = [
            ['EMP004', 'Sneha', 'Deshmukh', 'female', 'DES', 'GFX', 'ENG', 'STAFF', $shift03->id, $locHq->id],
            ['EMP005', 'Vikram', 'Singh', 'male', 'FAB', 'CNC', 'TECH', 'STAFF', $shiftGs->id, $locHq->id],
            ['EMP006', 'Pooja', 'Mehta', 'female', 'OPS', null, 'ENG', 'STAFF', $shiftGs->id, $locHq->id],
            ['EMP007', 'Rohan', 'Jadhav', 'male', 'FAB', 'CNC', 'TECH', 'CONT', $shift05->id, $locHq->id],
            ['EMP008', 'Deepak', 'Yadav', 'male', 'INST', 'SITE', 'ENG', 'FIELD', $shift05->id, $locSite->id],
            ['EMP009', 'Ananya', 'Rao', 'female', 'DES', 'GFX', 'ENG', 'STAFF', $shift03->id, $locHq->id],
        ];

        $allEmployees = collect([$empAdmin, $empMgr, $empSup, $empExec]);
        foreach ($extraStaffSpecs as [$code, $fn, $ln, $gender, $deptKey, $secKey, $desigKey, $catKey, $shiftId, $locId]) {
            $allEmployees->push($this->upsertEmployee($code, [
                'first_name' => $fn,
                'last_name' => $ln,
                'gender' => $gender,
                'date_of_joining' => '2024-06-01',
                'company_id' => $company->id,
                'department_id' => $depts[$deptKey]->id,
                'section_id' => $secKey ? $sections[$secKey]->id : null,
                'designation_id' => $desigs[$desigKey]->id,
                'category_id' => $cats[$catKey]->id,
                'extra_classification_id' => $extra->id,
                'l1_approver_id' => $empMgr->id,
                'l2_approver_id' => $empAdmin->id,
                'backup_approver_id' => $empSup->id,
                'default_shift_id' => $shiftId,
                'work_location_id' => $locId,
            ]));
        }

        // Backup Officer Delegation for testing Backup tab
        ApprovalDelegation::firstOrCreate(
            ['delegator_employee_id' => $empMgr->id, 'backup_employee_id' => $empSup->id, 'module' => 'all'],
            ['start_date' => now()->subDays(2)->toDateString(), 'end_date' => now()->addDays(10)->toDateString(), 'is_active' => true, 'reason' => 'Site travel delegation']
        );

        // 4. Seed 10 days of Attendance Punches & Daily Rollups
        $calc = app(AttendanceCalculationService::class);
        foreach (range(9, 0) as $daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);
            $dateStr = $date->toDateString();
            if ($date->dayOfWeek === 0) {
                continue;
            }

            foreach ($allEmployees as $idx => $emp) {
                // Leave EMP007 & EMP008 absent today for Absent Roster testing
                if ($daysAgo === 0 && in_array($emp->emp_code, ['EMP007', 'EMP008'], true)) {
                    $calc->recalculateDay($emp, $dateStr);

                    continue;
                }

                if (AttendancePunch::where('employee_id', $emp->id)->whereDate('punch_date', $dateStr)->exists()) {
                    continue;
                }

                $isLate = ($idx % 3 === 0 && $daysAgo % 2 === 0);
                $inHour = $isLate ? '10:10:00' : '09:20:00';
                $outHour = ($idx === 5 && $daysAgo % 2 === 1) ? '20:15:00' : '18:35:00';

                AttendancePunch::create([
                    'employee_id' => $emp->id,
                    'punched_at' => $dateStr.' '.$inHour,
                    'punch_date' => $dateStr,
                    'direction' => 'in',
                    'channel' => $idx % 2 === 0 ? 'mobile' : 'kiosk',
                    'work_location_id' => $emp->work_location_id,
                    'status' => 'valid',
                ]);
                AttendancePunch::create([
                    'employee_id' => $emp->id,
                    'punched_at' => $dateStr.' '.$outHour,
                    'punch_date' => $dateStr,
                    'direction' => 'out',
                    'channel' => $idx % 2 === 0 ? 'mobile' : 'kiosk',
                    'work_location_id' => $emp->work_location_id,
                    'status' => 'valid',
                ]);

                $calc->recalculateDay($emp, $dateStr);
            }
        }

        // Sample Regularization Requests
        AttendanceRequest::firstOrCreate(
            ['employee_id' => $empExec->id, 'attendance_date' => now()->subDays(2)->toDateString()],
            [
                'request_type' => 'missed_out',
                'requested_out_at' => now()->subDays(2)->setTime(18, 45),
                'reason' => 'Client site power outage while clocking out',
                'approval_status' => 'pending_l1',
                'current_level' => 1,
                'current_approver_id' => $empMgr->id,
            ]
        );

        // Ensure at least one OT approval row exists
        $sampleDay = AttendanceDay::where('overtime_minutes', '>', 0)->first();
        if ($sampleDay) {
            OvertimeApproval::firstOrCreate(
                ['attendance_day_id' => $sampleDay->id],
                ['employee_id' => $sampleDay->employee_id, 'attendance_date' => $sampleDay->attendance_date, 'computed_ot_minutes' => $sampleDay->overtime_minutes]
            );
        }

        // 5. Leave Balances, Holidays, Requests & FWA
        $fy = FinancialYearHelper::current();
        $balService = app(LeaveBalanceService::class);
        $cl = LeaveType::where('code', 'CL')->firstOrFail();
        $sl = LeaveType::where('code', 'SL')->firstOrFail();
        $el = LeaveType::where('code', 'EL')->firstOrFail();

        foreach ($allEmployees as $emp) {
            if ($emp->leave_seeded ?? false) {
                continue;
            }
            $existingCl = \SignageFlow\Hrms\Models\LeaveBalance::where('employee_id', $emp->id)->where('leave_type_id', $cl->id)->where('financial_year', $fy)->exists();
            if (! $existingCl) {
                $balService->mutateBalance($emp, $cl, $fy, 'opening', 8);
                $balService->mutateBalance($emp, $sl, $fy, 'opening', 5);
                $balService->mutateBalance($emp, $el, $fy, 'opening', 12);
            }
        }

        Holiday::firstOrCreate(
            ['financial_year' => $fy, 'name' => 'Republic Day', 'start_date' => Carbon::create((int) explode('-', $fy)[1], 1, 26)->toDateString()],
            ['end_date' => Carbon::create((int) explode('-', $fy)[1], 1, 26)->toDateString(), 'holiday_type' => 'public_holiday']
        );

        LeaveRequest::firstOrCreate(
            ['employee_id' => $empExec->id, 'start_date' => now()->addDays(3)->toDateString(), 'leave_type_id' => $cl->id],
            [
                'financial_year' => $fy,
                'end_date' => now()->addDays(4)->toDateString(),
                'day_mode' => 'full',
                'total_days' => 2,
                'reason' => 'Personal family event',
                'apply_mode' => 'self',
                'approval_status' => 'pending_l1',
                'current_level' => 1,
                'current_approver_id' => $empMgr->id,
                'l1_approver_id' => $empMgr->id,
                'l2_approver_id' => $empAdmin->id,
            ]
        );

        LeaveRequest::firstOrCreate(
            ['employee_id' => $empSup->id, 'start_date' => now()->addDays(7)->toDateString(), 'leave_type_id' => $el->id],
            [
                'financial_year' => $fy,
                'end_date' => now()->addDays(7)->toDateString(),
                'day_mode' => 'first_half',
                'total_days' => 0.5,
                'reason' => 'Bank & passport appointment',
                'apply_mode' => 'self',
                'approval_status' => 'pending_l2',
                'current_level' => 2,
                'current_approver_id' => $empAdmin->id,
                'l1_approver_id' => $empMgr->id,
                'l1_status' => 'approved',
                'l2_approver_id' => $empAdmin->id,
            ]
        );

        $wfh = FwaPolicy::where('code', 'WFH')->first();
        if ($wfh) {
            FwaRequest::firstOrCreate(
                ['employee_id' => $empSup->id, 'fwa_policy_id' => $wfh->id, 'start_date' => now()->addDays(2)->toDateString()],
                ['financial_year' => $fy, 'end_date' => now()->addDays(2)->toDateString(), 'reason' => 'Artwork finalization from home', 'approval_status' => 'pending_l1', 'current_approver_id' => $empMgr->id]
            );
        }

        CompOff::firstOrCreate(
            ['employee_id' => $empExec->id, 'worked_date' => now()->subDays(6)->toDateString()],
            ['financial_year' => $fy, 'days_earned' => 1.0, 'expires_at' => now()->addDays(60)->toDateString(), 'status' => 'approved', 'remarks' => 'Sunday installation support']
        );

        // 6. Phase 2 Sample Salary Structures, Loans & E-Claims
        $monthlyGroup = PayGroup::where('code', 'MONTHLY_STD')->first();
        $basicComp = SalaryComponent::where('code', 'BASIC')->first();
        $hraComp = SalaryComponent::where('code', 'HRA')->first();
        $convComp = SalaryComponent::where('code', 'CONV')->first();
        $specComp = SalaryComponent::where('code', 'SPEC')->first();

        if ($monthlyGroup && $basicComp) {
            foreach ($allEmployees as $idx => $emp) {
                if (SalaryStructure::where('employee_id', $emp->id)->where('is_active', true)->exists()) {
                    continue;
                }
                $gross = 18000 + (($allEmployees->count() - $idx) * 2500);
                $basic = round($gross * 0.50, 2);
                $hra = round($basic * 0.50, 2);
                $conv = 1600.00;
                $spec = max(0, round($gross - $basic - $hra - $conv, 2));

                $structure = SalaryStructure::create([
                    'employee_id' => $emp->id,
                    'pay_group_id' => $monthlyGroup->id,
                    'effective_from' => '2026-04-01',
                    'monthly_ctc' => $gross + 1800,
                    'monthly_gross' => $gross,
                    'basic_salary' => $basic,
                    'is_active' => true,
                ]);

                SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $basicComp->id, 'amount' => $basic]);
                if ($hraComp) {
                    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $hraComp->id, 'amount' => $hra]);
                }
                if ($convComp) {
                    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $convComp->id, 'amount' => $conv]);
                }
                if ($specComp) {
                    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $specComp->id, 'amount' => $spec]);
                }
            }
        }

        if (! Loan::where('loan_number', 'LN-2026-001')->exists()) {
            $loan = Loan::create([
                'loan_number' => 'LN-2026-001',
                'employee_id' => $empExec->id,
                'loan_type' => 'salary_advance',
                'principal_amount' => 12000,
                'interest_rate' => 0,
                'tenure_months' => 4,
                'emi_amount' => 3000,
                'disbursed_date' => now()->subDays(20)->toDateString(),
                'start_deduction_date' => now()->startOfMonth()->toDateString(),
                'outstanding_amount' => 12000,
                'status' => 'active',
                'remarks' => 'Sample salary advance',
            ]);
            for ($m = 0; $m < 4; $m++) {
                LoanInstallment::create([
                    'loan_id' => $loan->id,
                    'installment_no' => $m + 1,
                    'due_date' => now()->startOfMonth()->addMonths($m)->toDateString(),
                    'emi_amount' => 3000,
                    'principal_component' => 3000,
                    'interest_component' => 0,
                    'status' => 'pending',
                ]);
            }
        }

        Claim::firstOrCreate(
            ['claim_number' => 'CLM-2026-001'],
            [
                'employee_id' => $empExec->id,
                'claim_date' => now()->subDays(3)->toDateString(),
                'category' => 'Site Travel',
                'amount' => 850,
                'description' => 'BKC client site travel & parking',
                'approval_status' => 'approved',
                'current_level' => 1,
                'current_approver_id' => $empMgr->id,
            ]
        );
    }

    protected function upsertEmployee(string $code, array $attributes): Employee
    {
        $first = $attributes['first_name'];
        $last = $attributes['last_name'] ?? '';

        return Employee::firstOrCreate(
            ['emp_code' => $code],
            array_merge([
                'full_name' => trim($first.' '.$last),
                'employment_status' => 'active',
                'employment_type' => 'permanent',
                'geofence_mode' => 'inherit',
                'mobile_attendance_enabled' => true,
                'kiosk_pin_hash' => Hash::make('1234'),
                'qr_badge_token' => Str::random(32),
            ], $attributes)
        );
    }
}
