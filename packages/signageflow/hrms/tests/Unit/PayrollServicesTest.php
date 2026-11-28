<?php

namespace SignageFlow\Hrms\Tests\Unit;

use Illuminate\Support\Facades\Storage;
use SignageFlow\Hrms\Models\Claim;
use SignageFlow\Hrms\Models\Company;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\Loan;
use SignageFlow\Hrms\Models\LoanInstallment;
use SignageFlow\Hrms\Models\PayGroup;
use SignageFlow\Hrms\Models\SalaryComponent;
use SignageFlow\Hrms\Models\SalaryStructure;
use SignageFlow\Hrms\Models\SalaryStructureItem;
use SignageFlow\Hrms\Models\StatutoryConfig;
use SignageFlow\Hrms\Services\BankSubmissionService;
use SignageFlow\Hrms\Services\FormulaEvaluator;
use SignageFlow\Hrms\Services\HrmsNotificationService;
use SignageFlow\Hrms\Services\PayRunProcessingService;
use SignageFlow\Hrms\Services\StatutoryCalculationService;
use SignageFlow\Hrms\Services\TallyGlService;
use SignageFlow\Hrms\Tests\HrmsTestCase;

class PayrollServicesTest extends HrmsTestCase
{
    public function test_formula_evaluator_evaluates_arithmetic_and_variables(): void
    {
        $evaluator = new FormulaEvaluator;

        $this->assertEquals(50.0, $evaluator->evaluate('10 + 20 * 2'));
        $this->assertEquals(60.0, $evaluator->evaluate('(10 + 20) * 2'));

        $vars = ['BASIC' => 20000.0, 'HRA' => 10000.0];
        $this->assertEquals(10000.0, $evaluator->evaluate('BASIC * 0.5', $vars));
        $this->assertEquals(15000.0, $evaluator->evaluate('(BASIC + HRA) / 2', $vars));
    }

    public function test_statutory_calculation_service(): void
    {
        $company = Company::create(['code' => 'TEST_STAT', 'name' => 'Stat Corp', 'is_active' => true]);
        $dept = Department::create(['code' => 'STAT_DEPT', 'name' => 'Stat Dept', 'is_active' => true]);

        StatutoryConfig::create([
            'financial_year' => '2026-2027',
            'epf_employee_rate' => 12.0,
            'epf_employer_rate' => 12.0,
            'epf_wage_ceiling' => 15000.0,
            'esic_employee_rate' => 0.75,
            'esic_employer_rate' => 3.25,
            'esic_wage_ceiling' => 21000.0,
            'pt_state_slabs' => [
                'MH' => [
                    ['min' => 0, 'max' => 7500, 'tax' => 0],
                    ['min' => 7501, 'max' => 10000, 'tax' => 175],
                    ['min' => 10001, 'max' => 9999999, 'tax' => 200, 'feb_tax' => 300],
                ],
            ],
            'lwf_rules' => [
                'MH' => ['employee' => 25.0, 'employer' => 75.0, 'months' => [6, 12]],
            ],
            'is_active' => true,
        ]);

        $statService = new StatutoryCalculationService;

        // High earner (capped at EPF ceiling 15,000, no ESIC because gross > 21,000)
        $empHigh = Employee::create([
            'emp_code' => 'EMP-HIGH',
            'first_name' => 'Rajesh',
            'last_name' => 'Kumar',
            'full_name' => 'Rajesh Kumar',
            'date_of_joining' => '2025-01-01',
            'company_id' => $company->id,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'epf_enabled' => true,
            'esic_enabled' => true,
            'pt_enabled' => true,
            'lwf_enabled' => true,
            'pt_state_code' => 'MH',
        ]);

        $resHigh = $statService->calculate(
            employee: $empHigh,
            financialYear: '2026-2027',
            periodMonth: 5,
            pfWages: 25000.0,
            esicWages: 40000.0,
            ptWages: 40000.0
        );

        $this->assertEquals(1800.0, $resHigh['epf_employee']); // 12% of 15,000
        $this->assertEquals(1800.0, $resHigh['epf_employer']);
        $this->assertEquals(0.0, $resHigh['esic_employee']); // gross > 21,000
        $this->assertEquals(200.0, $resHigh['pt_amount']); // May MH PT

        // February MH PT rule (300 instead of 200)
        $resFeb = $statService->calculate(
            employee: $empHigh,
            financialYear: '2026-2027',
            periodMonth: 2,
            pfWages: 20000.0,
            esicWages: 40000.0,
            ptWages: 40000.0
        );
        $this->assertEquals(300.0, $resFeb['pt_amount']);

        // Low earner under ESIC ceiling
        $empLow = Employee::create([
            'emp_code' => 'EMP-LOW',
            'first_name' => 'Pooja',
            'last_name' => 'Sharma',
            'full_name' => 'Pooja Sharma',
            'date_of_joining' => '2025-01-01',
            'company_id' => $company->id,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'epf_enabled' => true,
            'esic_enabled' => true,
            'pt_enabled' => true,
            'lwf_enabled' => true,
            'pt_state_code' => 'MH',
        ]);

        $resLow = $statService->calculate(
            employee: $empLow,
            financialYear: '2026-2027',
            periodMonth: 6,
            pfWages: 10000.0,
            esicWages: 15000.0,
            ptWages: 15000.0
        );
        $this->assertEquals(1200.0, $resLow['epf_employee']); // 12% of 10,000
        $this->assertEquals(113.0, $resLow['esic_employee']); // ceil(15,000 * 0.75%) = 113
        $this->assertEquals(487.5, $resLow['esic_employer']); // 3.25% of 15,000
        $this->assertEquals(25.0, $resLow['lwf_amount']); // June LWF
    }

    public function test_payrun_3_stage_lifecycle_with_loans_claims_and_publishing(): void
    {
        Storage::fake(config('hrms.storage_disk', 'local'));

        StatutoryConfig::create([
            'financial_year' => '2026-2027',
            'epf_employee_rate' => 12.0,
            'epf_employer_rate' => 12.0,
            'epf_wage_ceiling' => 15000.0,
            'esic_employee_rate' => 0.75,
            'esic_employer_rate' => 3.25,
            'esic_wage_ceiling' => 21000.0,
            'pt_state_slabs' => [
                'MH' => [
                    ['min' => 10001, 'max' => 9999999, 'tax' => 200],
                ],
            ],
            'lwf_rules' => [
                'MH' => ['employee' => 25.0, 'employer' => 75.0, 'months' => [6, 12]],
            ],
            'is_active' => true,
        ]);

        $company = Company::create(['code' => 'TEST_P2', 'name' => 'Test Corp', 'is_active' => true]);
        $dept = Department::create(['code' => 'ENG_P2', 'name' => 'Engineering', 'is_active' => true]);

        $emp = Employee::create([
            'emp_code' => 'EMP-P2-01',
            'first_name' => 'Anita',
            'last_name' => 'Desai',
            'full_name' => 'Anita Desai',
            'work_email' => 'anita.p2@test.local',
            'date_of_joining' => '2025-01-01',
            'company_id' => $company->id,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'epf_enabled' => true,
            'esic_enabled' => true,
            'pt_enabled' => true,
            'lwf_enabled' => true,
            'pt_state_code' => 'MH',
            'bank_name' => 'HDFC Bank',
            'bank_account_no' => '50100123456789',
            'bank_ifsc' => 'HDFC0001234',
        ]);

        $payGroup = PayGroup::create([
            'code' => 'M-STD',
            'name' => 'Monthly Standard',
            'pay_frequency' => 'monthly',
            'is_active' => true,
        ]);

        $basicComp = SalaryComponent::create([
            'code' => 'BASIC',
            'name' => 'Basic Salary',
            'type' => 'earning',
            'calc_type' => 'fixed',
            'formula_or_value' => '25000',
            'is_pf_wage' => true,
            'is_esic_wage' => true,
            'is_pt_wage' => true,
            'is_active' => true,
        ]);

        $hraComp = SalaryComponent::create([
            'code' => 'HRA',
            'name' => 'House Rent Allowance',
            'type' => 'earning',
            'calc_type' => 'percent_of_basic',
            'formula_or_value' => '50',
            'is_pf_wage' => false,
            'is_esic_wage' => true,
            'is_pt_wage' => true,
            'is_active' => true,
        ]);

        $struct = SalaryStructure::create([
            'employee_id' => $emp->id,
            'pay_group_id' => $payGroup->id,
            'effective_from' => '2026-04-01',
            'monthly_gross' => 37500.0,
            'basic_salary' => 25000.0,
            'is_active' => true,
        ]);

        SalaryStructureItem::create(['salary_structure_id' => $struct->id, 'salary_component_id' => $basicComp->id, 'amount' => 25000.0]);
        SalaryStructureItem::create(['salary_structure_id' => $struct->id, 'salary_component_id' => $hraComp->id, 'amount' => 12500.0]);

        // Create an active loan with a due installment in Nov 2026
        $loan = Loan::create([
            'loan_number' => 'LN-2026-001',
            'employee_id' => $emp->id,
            'loan_type' => 'salary_advance',
            'principal_amount' => 12000.0,
            'interest_rate' => 0.0,
            'tenure_months' => 6,
            'emi_amount' => 2000.0,
            'disbursed_date' => '2026-10-01',
            'start_deduction_date' => '2026-11-01',
            'outstanding_amount' => 12000.0,
            'status' => 'active',
        ]);

        LoanInstallment::create([
            'loan_id' => $loan->id,
            'installment_no' => 1,
            'due_date' => '2026-11-10',
            'emi_amount' => 2000.0,
            'principal_component' => 2000.0,
            'interest_component' => 0.0,
            'status' => 'pending',
        ]);

        // Create an approved claim
        $claim = Claim::create([
            'claim_number' => 'CLM-2026-001',
            'employee_id' => $emp->id,
            'category' => 'Travel',
            'claim_date' => '2026-11-05',
            'amount' => 1500.0,
            'description' => 'Travel reimbursement',
            'approval_status' => 'approved',
        ]);

        $statCalc = new StatutoryCalculationService;
        $notifier = new HrmsNotificationService;
        $processor = new PayRunProcessingService($statCalc, $notifier);

        // Stage 1: Create & Initialize PayRun
        $payRun = $processor->createAndInitialize([
            'pay_group_id' => $payGroup->id,
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-30',
        ]);

        $this->assertEquals('initialized', $payRun->stage);
        $this->assertCount(1, $payRun->items);

        $item = $payRun->items->first();
        $this->assertEquals(39000.0, $item->gross_earnings); // 37500 structure + 1500 claim
        $this->assertEquals(1800.0, $item->epf_employee);
        $this->assertEquals(200.0, $item->pt_amount);
        $this->assertEquals(2000.0, $item->loan_emi_amount);
        $this->assertEquals(1500.0, $item->claim_reimbursement_amount);

        // Stage 2: Submit and Approve
        $payRun = $processor->submitForApproval($payRun);
        $this->assertEquals('pending_l1', $payRun->stage);

        $payRun = $processor->approveStage($payRun, 1);
        $this->assertEquals('approved', $payRun->stage);

        // Loan installment should now be deducted & outstanding decreased
        $loan->refresh();
        $this->assertEquals(10000.0, $loan->outstanding_amount);
        $this->assertEquals('deducted', LoanInstallment::where('loan_id', $loan->id)->first()->status);

        // Claim should be marked as paid
        $claim->refresh();
        $this->assertEquals('paid', $claim->approval_status);

        // Stage 3: Publish Payslips
        $payRun = $processor->publish($payRun, 1);
        $this->assertEquals('published', $payRun->stage);

        $item->refresh();
        $this->assertTrue($item->is_published);
        $this->assertNotEmpty($item->payslip_pdf_path);
        Storage::disk(config('hrms.storage_disk', 'local'))->assertExists($item->payslip_pdf_path);

        // Tally GL Service: Generate Batch & XML
        $tallyService = new TallyGlService;
        $glBatch = $tallyService->createBatch($payRun, '2026-11-30');
        $this->assertNotNull($glBatch);
        $this->assertEquals($glBatch->total_debit, $glBatch->total_credit);

        $xml = $tallyService->toTallyXml($glBatch);
        $this->assertStringContainsString('<TALLYMESSAGE', $xml);
        $this->assertStringContainsString('<VOUCHER', $xml);
        $this->assertStringContainsString($payRun->run_code, $xml);

        // Bank Submission Service: Generate Spreadsheet
        $bankService = new BankSubmissionService;
        $bankFile = $bankService->buildSpreadsheet($payRun);
        $this->assertNotNull($bankFile['writer']);
        $this->assertEquals('xlsx', $bankFile['extension']);
    }
}
