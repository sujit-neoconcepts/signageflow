<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Payroll;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\Loan;
use SignageFlow\Hrms\Models\PayRun;
use SignageFlow\Hrms\Models\PayRunItem;
use SignageFlow\Hrms\Models\SalaryRevision;
use SignageFlow\Hrms\Models\SalaryStructure;

class PayrollReportController extends Controller
{
    public const REPORTS = [
        'disbursement_summary' => 'Disbursement Summary',
        'payroll_detail' => 'Payroll Detail',
        'salary_summary' => 'Salary Summary',
        'weekly_salary' => 'Weekly Salary',
        'daily_rate' => 'Daily Rate',
        'yearly_net_payable' => 'Yearly Net Payable',
        'salary_comparison' => 'Salary Comparison',
        'salary_revision' => 'Salary Revision',
        'salary_structure' => 'Employee Salary Structure',
        'employment_history' => 'Employment History',
        'loan_report' => 'Loan Report',
        'statutory_summary' => 'Statutory Summary (EPF / ESIC / PT / LWF)',
    ];

    public function __construct()
    {
        $this->middleware('can:hrmsPayrollReport_list')->only(['index']);
        $this->middleware('can:hrmsPayrollReport_export')->only(['export']);
    }

    public function index(Request $request)
    {
        $report = $request->input('report', 'disbursement_summary');
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $departmentId = $request->input('department_id');

        [$columns, $rows] = $this->buildReport($report, $year, $month, $departmentId ? (int) $departmentId : null);

        return Inertia::render('Hrms/Payroll/Reports', [
            'report' => $report,
            'reports' => self::REPORTS,
            'reportsList' => self::REPORTS,
            'year' => $year,
            'month' => $month,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'department_id' => $departmentId,
            ],
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'columns' => $columns,
            'rows' => $rows,
            'resourceNeo' => [
                'resourceName' => 'hrms.payroll.reports',
                'resourceTitle' => 'Payroll Reports',
                'actions' => ['r'],
            ],
        ]);
    }

    public function export(Request $request)
    {
        $report = $request->input('report', 'disbursement_summary');
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $departmentId = $request->input('department_id');

        [$columns, $rows] = $this->buildReport($report, $year, $month, $departmentId ? (int) $departmentId : null);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($columns, null, 'A1');

        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_values($row), null, 'A'.$r++);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "hrms-{$report}-{$year}-{$month}.xlsx");
    }

    protected function buildReport(string $report, int $year, int $month, ?int $departmentId): array
    {
        $baseItems = PayRunItem::query()
            ->with(['payRun.payGroup', 'employee.department', 'employee.designation'])
            ->where('is_initialized', true)
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $departmentId)));

        return match ($report) {
            'payroll_detail' => [
                ['Run Code', 'Emp Code', 'Employee', 'Department', 'Payable Days', 'Gross', 'EPF', 'ESIC', 'PT', 'Loan EMI', 'Claims', 'Net Wages'],
                $baseItems->whereHas('payRun', fn ($q) => $q->where('period_year', $year)->where('period_month', $month))
                    ->get()
                    ->map(fn ($i) => [
                        $i->payRun?->run_code,
                        $i->employee?->emp_code,
                        $i->employee?->full_name,
                        $i->employee?->department?->name ?? '-',
                        $i->payable_days,
                        $i->gross_earnings,
                        $i->epf_employee,
                        $i->esic_employee,
                        $i->pt_amount,
                        $i->loan_emi_amount,
                        $i->claim_reimbursement_amount,
                        $i->net_wages,
                    ])->all(),
            ],
            'salary_summary' => [
                ['Department', 'Employees', 'Total Gross', 'Total Deductions', 'Total Net'],
                $baseItems->whereHas('payRun', fn ($q) => $q->where('period_year', $year)->where('period_month', $month))
                    ->get()
                    ->groupBy(fn ($i) => $i->employee?->department?->name ?? 'Unassigned')
                    ->map(fn ($grp, $dept) => [
                        $dept,
                        $grp->count(),
                        round($grp->sum('gross_earnings'), 2),
                        round($grp->sum('total_deductions'), 2),
                        round($grp->sum('net_wages'), 2),
                    ])->values()->all(),
            ],
            'weekly_salary' => [
                ['Run Code', 'Week #', 'Emp Code', 'Employee', 'Payable Days', 'OT Hours', 'Gross', 'Net Wages'],
                $baseItems->whereHas('payRun', fn ($q) => $q->where('pay_frequency', 'weekly')->where('period_year', $year))
                    ->get()
                    ->map(fn ($i) => [
                        $i->payRun?->run_code,
                        $i->payRun?->week_number ?? '-',
                        $i->employee?->emp_code,
                        $i->employee?->full_name,
                        $i->payable_days,
                        $i->ot_hours,
                        $i->gross_earnings,
                        $i->net_wages,
                    ])->all(),
            ],
            'daily_rate' => [
                ['Emp Code', 'Employee', 'Department', 'Monthly Gross', 'Per-Day Rate (30d)'],
                SalaryStructure::with('employee.department')
                    ->where('is_active', true)
                    ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $departmentId)))
                    ->get()
                    ->map(fn ($s) => [
                        $s->employee?->emp_code,
                        $s->employee?->full_name,
                        $s->employee?->department?->name ?? '-',
                        $s->monthly_gross,
                        round($s->monthly_gross / 30, 2),
                    ])->all(),
            ],
            'yearly_net_payable' => [
                ['Emp Code', 'Employee', 'Runs Count', 'Total Gross', 'Total Deductions', 'Total Net Payable'],
                $baseItems->whereHas('payRun', fn ($q) => $q->where('period_year', $year))
                    ->get()
                    ->groupBy('employee_id')
                    ->map(fn ($grp) => [
                        $grp->first()?->employee?->emp_code,
                        $grp->first()?->employee?->full_name,
                        $grp->count(),
                        round($grp->sum('gross_earnings'), 2),
                        round($grp->sum('total_deductions'), 2),
                        round($grp->sum('net_wages'), 2),
                    ])->values()->all(),
            ],
            'salary_comparison' => [
                ['Emp Code', 'Employee', 'Current Net', 'Previous Net', 'Variance'],
                PayRunItem::with('employee')
                    ->where('is_initialized', true)
                    ->whereHas('payRun', fn ($q) => $q->where('period_year', $year)->where('period_month', $month))
                    ->get()
                    ->map(function ($curr) use ($year, $month) {
                        $prevDate = now()->setDate($year, $month, 1)->subMonth();
                        $prev = PayRunItem::where('employee_id', $curr->employee_id)
                            ->whereHas('payRun', fn ($q) => $q->where('period_year', $prevDate->year)->where('period_month', $prevDate->month))
                            ->value('net_wages') ?? 0;

                        return [
                            $curr->employee?->emp_code,
                            $curr->employee?->full_name,
                            $curr->net_wages,
                            $prev,
                            round($curr->net_wages - (float) $prev, 2),
                        ];
                    })->all(),
            ],
            'salary_revision' => [
                ['Emp Code', 'Employee', 'Prev CTC', 'New CTC', 'Increment %', 'Effective From', 'Remarks'],
                SalaryRevision::with('employee')
                    ->latest('effective_from')
                    ->get()
                    ->map(fn ($r) => [
                        $r->employee?->emp_code,
                        $r->employee?->full_name,
                        $r->previous_ctc,
                        $r->new_ctc,
                        $r->increment_percent.'%',
                        $r->effective_from?->format('Y-m-d'),
                        $r->remarks ?? '-',
                    ])->all(),
            ],
            'salary_structure' => [
                ['Emp Code', 'Employee', 'Pay Group', 'Basic', 'Monthly Gross', 'Monthly CTC', 'Effective From'],
                SalaryStructure::with(['employee', 'payGroup'])
                    ->where('is_active', true)
                    ->get()
                    ->map(fn ($s) => [
                        $s->employee?->emp_code,
                        $s->employee?->full_name,
                        $s->payGroup?->name ?? '-',
                        $s->basic_salary,
                        $s->monthly_gross,
                        $s->monthly_ctc,
                        $s->effective_from?->format('Y-m-d'),
                    ])->all(),
            ],
            'employment_history' => [
                ['Emp Code', 'Employee', 'Department', 'Designation', 'Joined Date', 'Status', 'Exit Date'],
                Employee::with(['department', 'designation'])
                    ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
                    ->orderBy('emp_code')
                    ->get()
                    ->map(fn ($e) => [
                        $e->emp_code,
                        $e->full_name,
                        $e->department?->name ?? '-',
                        $e->designation?->name ?? '-',
                        $e->date_of_joining?->format('Y-m-d') ?? '-',
                        $e->employment_status,
                        $e->date_of_exit?->format('Y-m-d') ?? '-',
                    ])->all(),
            ],
            'loan_report' => [
                ['Loan #', 'Emp Code', 'Employee', 'Type', 'Principal', 'EMI', 'Tenure', 'Outstanding', 'Status'],
                Loan::with('employee')
                    ->latest('id')
                    ->get()
                    ->map(fn ($l) => [
                        $l->loan_number,
                        $l->employee?->emp_code,
                        $l->employee?->full_name,
                        $l->loan_type,
                        $l->principal_amount,
                        $l->emi_amount,
                        $l->tenure_months,
                        $l->outstanding_amount,
                        $l->status,
                    ])->all(),
            ],
            'statutory_summary' => [
                ['Emp Code', 'Employee', 'EPF (Emp)', 'EPF (Er)', 'ESIC (Emp)', 'ESIC (Er)', 'PT', 'LWF'],
                $baseItems->whereHas('payRun', fn ($q) => $q->where('period_year', $year)->where('period_month', $month))
                    ->get()
                    ->map(fn ($i) => [
                        $i->employee?->emp_code,
                        $i->employee?->full_name,
                        $i->epf_employee,
                        $i->epf_employer,
                        $i->esic_employee,
                        $i->esic_employer,
                        $i->pt_amount,
                        $i->lwf_amount,
                    ])->all(),
            ],
            default => [
                ['Run Code', 'Pay Group', 'Cycle', 'Period', 'Total Gross', 'Total Deductions', 'Total Net', 'Stage'],
                PayRun::with('payGroup')
                    ->where('period_year', $year)
                    ->latest('id')
                    ->get()
                    ->map(fn ($p) => [
                        $p->run_code,
                        $p->payGroup?->name ?? '-',
                        $p->pay_frequency,
                        $p->period_start?->format('Y-m-d').' to '.$p->period_end?->format('Y-m-d'),
                        $p->total_gross,
                        $p->total_deductions,
                        $p->total_net,
                        $p->stage,
                    ])->all(),
            ],
        };
    }
}
