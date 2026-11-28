<?php

namespace SignageFlow\Hrms\Database\Seeders;

use Illuminate\Database\Seeder;
use SignageFlow\Hrms\Models\AttendanceAllowance;
use SignageFlow\Hrms\Models\BankTemplate;
use SignageFlow\Hrms\Models\FwaPolicy;
use SignageFlow\Hrms\Models\LeaveType;
use SignageFlow\Hrms\Models\NotificationTemplate;
use SignageFlow\Hrms\Models\PayGroup;
use SignageFlow\Hrms\Models\SalaryComponent;
use SignageFlow\Hrms\Models\Shift;
use SignageFlow\Hrms\Models\StatutoryConfig;
use SignageFlow\Hrms\Services\FinancialYearHelper;

class HrmsDefaultPresetsSeeder extends Seeder
{
    public function run(): void
    {
        Shift::firstOrCreate(
            ['code' => 'GS'],
            [
                'name' => 'General Shift (09:30 - 18:30)',
                'start_time' => '09:30:00',
                'end_time' => '18:30:00',
                'is_overnight' => false,
                'grace_in_minutes' => 15,
                'grace_out_minutes' => 10,
                'break_minutes' => 60,
                'half_day_minutes' => 240,
                'full_day_minutes' => 480,
                'ot_eligible' => true,
                'ot_min_threshold_minutes' => 30,
                'ot_multiplier' => 1.50,
                'week_off_days' => [0],
            ]
        );

        $leaveTypes = [
            ['code' => 'CL', 'name' => 'Casual Leave', 'is_paid' => true, 'annual_quota' => 12, 'max_carry_forward' => 0, 'accrual_frequency' => 'monthly'],
            ['code' => 'SL', 'name' => 'Sick Leave', 'is_paid' => true, 'annual_quota' => 7, 'max_carry_forward' => 0, 'accrual_frequency' => 'monthly', 'requires_attachment' => true, 'attachment_after_days' => 2],
            ['code' => 'EL', 'name' => 'Earned / Privilege Leave', 'is_paid' => true, 'annual_quota' => 15, 'max_carry_forward' => 30, 'allow_encashment' => true, 'accrual_frequency' => 'monthly'],
            ['code' => 'LOP', 'name' => 'Unpaid Leave (LOP)', 'is_paid' => false, 'annual_quota' => 365, 'max_carry_forward' => 0, 'accrual_frequency' => 'none'],
            ['code' => 'CO', 'name' => 'Compensatory Off', 'is_paid' => true, 'annual_quota' => 0, 'max_carry_forward' => 0, 'accrual_frequency' => 'none'],
            ['code' => 'ML', 'name' => 'Maternity Leave', 'is_paid' => true, 'annual_quota' => 182, 'max_carry_forward' => 0, 'accrual_frequency' => 'none', 'gender_restriction' => 'female', 'is_statutory' => true],
            ['code' => 'PL', 'name' => 'Paternity Leave', 'is_paid' => true, 'annual_quota' => 15, 'max_carry_forward' => 0, 'accrual_frequency' => 'none', 'gender_restriction' => 'male', 'is_statutory' => true],
        ];

        foreach ($leaveTypes as $lt) {
            LeaveType::firstOrCreate(['code' => $lt['code']], $lt);
        }

        FwaPolicy::firstOrCreate(
            ['code' => 'WFH'],
            ['name' => 'Work From Home', 'mode' => 'wfh', 'max_days_per_month' => 4, 'requires_approval' => true]
        );

        AttendanceAllowance::firstOrCreate(
            ['code' => 'PUNCTUAL'],
            ['name' => 'Monthly Punctuality Allowance', 'amount' => 1000.00, 'max_absences_allowed' => 0, 'max_lates_allowed' => 2, 'min_present_days' => 22]
        );

        $templates = [
            ['event_key' => 'leave_applied', 'name' => 'Leave Application Submitted', 'subject_template' => 'Leave Request: {{employee_name}}', 'body_template' => '{{employee_name}} applied for {{leave_type}} ({{days}} days) from {{start_date}} to {{end_date}}.'],
            ['event_key' => 'leave_status_changed', 'name' => 'Leave Approved / Rejected', 'subject_template' => 'Leave Request {{status}}', 'body_template' => 'Your {{leave_type}} request ({{start_date}} to {{end_date}}) has been {{status}}.'],
            ['event_key' => 'attendance_regularized', 'name' => 'Attendance Regularization Update', 'subject_template' => 'Attendance Regularization {{status}}', 'body_template' => 'Your attendance regularization for {{date}} has been {{status}}.'],
            ['event_key' => 'payslip_published', 'name' => 'Payslip Published', 'subject_template' => 'Payslip Available: {{period}}', 'body_template' => 'Your payslip for {{period}} (Net Pay: INR {{net_wages}}) is now published.'],
        ];

        foreach ($templates as $tpl) {
            NotificationTemplate::firstOrCreate(['event_key' => $tpl['event_key']], $tpl);
        }

        // Phase 2 Payroll Presets
        $components = [
            ['code' => 'BASIC', 'name' => 'Basic Salary', 'type' => 'earning', 'calc_type' => 'fixed', 'formula_or_value' => '0', 'is_pf_wage' => true, 'is_esic_wage' => true, 'is_pt_wage' => true, 'sort_order' => 1],
            ['code' => 'HRA', 'name' => 'House Rent Allowance', 'type' => 'earning', 'calc_type' => 'percent_of_basic', 'formula_or_value' => '50', 'is_esic_wage' => true, 'is_pt_wage' => true, 'sort_order' => 2],
            ['code' => 'CONV', 'name' => 'Conveyance Allowance', 'type' => 'earning', 'calc_type' => 'fixed', 'formula_or_value' => '1600', 'is_esic_wage' => true, 'is_pt_wage' => true, 'sort_order' => 3],
            ['code' => 'SPEC', 'name' => 'Special Allowance', 'type' => 'earning', 'calc_type' => 'fixed', 'formula_or_value' => '0', 'is_esic_wage' => true, 'is_pt_wage' => true, 'sort_order' => 4],
            ['code' => 'LATE_DED', 'name' => 'Late / Early Out Deduction', 'type' => 'deduction', 'calc_type' => 'fixed', 'formula_or_value' => '0', 'sort_order' => 20],
        ];

        foreach ($components as $comp) {
            SalaryComponent::firstOrCreate(['code' => $comp['code']], $comp);
        }

        $bankTemplate = BankTemplate::firstOrCreate(
            ['code' => 'NEFT_STD'],
            [
                'name' => 'Standard NEFT Salary Transfer',
                'format' => 'csv',
                'is_default' => true,
                'columns_mapping' => [
                    'Emp Code' => 'emp_code',
                    'Beneficiary Name' => 'full_name',
                    'Bank Name' => 'bank_name',
                    'Account Number' => 'bank_account_no',
                    'IFSC Code' => 'bank_ifsc',
                    'Net Payable' => 'net_wages',
                    'PayRun Ref' => 'run_code',
                ],
            ]
        );

        PayGroup::firstOrCreate(
            ['code' => 'MONTHLY_STD'],
            [
                'name' => 'Monthly Staff',
                'pay_frequency' => 'monthly',
                'default_bank_template_id' => $bankTemplate->id,
                'tally_ledger_mapping' => [
                    'salary_expense' => 'Salaries & Wages',
                    'salary_payable' => 'Salary Payable',
                    'epf_payable' => 'EPF Payable',
                    'esic_payable' => 'ESIC Payable',
                    'pt_payable' => 'Professional Tax Payable',
                    'tds_payable' => 'TDS Payable',
                    'loan_recovery' => 'Staff Loans & Advances',
                    'reimbursements' => 'Staff Reimbursements Payable',
                ],
            ]
        );

        PayGroup::firstOrCreate(
            ['code' => 'WEEKLY_CREW'],
            [
                'name' => 'Weekly Site Crew',
                'pay_frequency' => 'weekly',
                'default_bank_template_id' => $bankTemplate->id,
            ]
        );

        StatutoryConfig::firstOrCreate(
            ['financial_year' => FinancialYearHelper::current()],
            [
                'epf_employee_rate' => 12.00,
                'epf_employer_rate' => 12.00,
                'epf_wage_ceiling' => 15000.00,
                'epf_admin_rate' => 1.00,
                'esic_employee_rate' => 0.75,
                'esic_employer_rate' => 3.25,
                'esic_wage_ceiling' => 21000.00,
                'pt_state_slabs' => [
                    'MH' => [
                        ['min' => 0, 'max' => 7500, 'tax' => 0],
                        ['min' => 7501, 'max' => 10000, 'tax' => 175],
                        ['min' => 10001, 'max' => 9999999, 'tax' => 200, 'feb_tax' => 300],
                    ],
                    'GJ' => [
                        ['min' => 0, 'max' => 12000, 'tax' => 0],
                        ['min' => 12001, 'max' => 9999999, 'tax' => 200],
                    ],
                    'KA' => [
                        ['min' => 0, 'max' => 24999, 'tax' => 0],
                        ['min' => 25000, 'max' => 9999999, 'tax' => 200],
                    ],
                ],
                'lwf_rules' => [
                    'MH' => ['employee' => 25, 'employer' => 75, 'months' => [6, 12]],
                ],
            ]
        );
    }
}
