<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Leave;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SignageFlow\Hrms\Models\CompOff;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\FwaRequest;
use SignageFlow\Hrms\Models\LeaveBalance;
use SignageFlow\Hrms\Models\LeaveRequest;
use SignageFlow\Hrms\Models\LeaveTransaction;
use SignageFlow\Hrms\Models\PermissionRequest;
use SignageFlow\Hrms\Services\FinancialYearHelper;

class LeaveReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsLeaveReport_list')->only(['index']);
        $this->middleware('can:hrmsLeaveReport_export')->only(['export']);
    }

    public function index(Request $request)
    {
        $reportType = $request->input('report', 'daily_leave');
        $fy = $request->input('financial_year', FinancialYearHelper::current());
        $from = Carbon::parse($request->input('from_date', now()->startOfMonth()->toDateString()))->toDateString();
        $to = Carbon::parse($request->input('to_date', now()->toDateString()))->toDateString();
        $orgFilters = $request->only(['company_id', 'department_id', 'section_id', 'designation_id', 'category_id', 'extra_classification_id']);

        $data = $this->buildReportData($reportType, $fy, $from, $to, $orgFilters);

        return Inertia::render('Hrms/Leave/Reports', [
            'reportType' => $reportType,
            'financialYear' => $fy,
            'fromDate' => $from,
            'toDate' => $to,
            'filters' => $orgFilters,
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request)
    {
        $reportType = $request->input('report', 'daily_leave');
        $fy = $request->input('financial_year', FinancialYearHelper::current());
        $from = Carbon::parse($request->input('from_date', now()->startOfMonth()->toDateString()))->toDateString();
        $to = Carbon::parse($request->input('to_date', now()->toDateString()))->toDateString();
        $orgFilters = $request->only(['company_id', 'department_id', 'section_id', 'designation_id', 'category_id', 'extra_classification_id']);

        $data = $this->buildReportData($reportType, $fy, $from, $to, $orgFilters);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($data['columns'], null, 'A1');

        $rowNum = 2;
        foreach ($data['rows'] as $row) {
            $sheet->fromArray(array_values($row), null, 'A'.$rowNum++);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "hrms-{$reportType}-{$fy}.xlsx");
    }

    protected function buildReportData(string $reportType, string $fy, string $from, string $to, array $orgFilters): array
    {
        $empIds = Employee::active()->filterByOrg($orgFilters)->pluck('id');

        if (in_array($reportType, ['leave_consolidate', 'leave_status'], true)) {
            $bals = LeaveBalance::with(['employee.department', 'leaveType'])
                ->whereIn('employee_id', $empIds)
                ->where('financial_year', $fy)
                ->get();

            return [
                'columns' => ['FY', 'Emp Code', 'Employee', 'Department', 'Leave Type', 'Opening', 'Accrued', 'Adj', 'Taken', 'Pending', 'Lapsed', 'Closing'],
                'rows' => $bals->map(fn ($b) => [
                    $b->financial_year,
                    $b->employee?->emp_code,
                    $b->employee?->full_name,
                    $b->employee?->department?->name,
                    $b->leaveType?->code,
                    $b->opening_balance,
                    $b->accrued,
                    $b->credited_adjustment,
                    $b->taken,
                    $b->pending,
                    $b->lapsed,
                    $b->closing_balance,
                ])->all(),
            ];
        }

        if ($reportType === 'leave_history') {
            $txns = LeaveTransaction::with(['employee', 'leaveType'])
                ->whereIn('employee_id', $empIds)
                ->where('financial_year', $fy)
                ->latest()
                ->take(500)
                ->get();

            return [
                'columns' => ['Date', 'Emp Code', 'Employee', 'Leave Type', 'Txn Type', 'Days', 'Balance After', 'Remarks'],
                'rows' => $txns->map(fn ($t) => [
                    $t->created_at->format('Y-m-d'),
                    $t->employee?->emp_code,
                    $t->employee?->full_name,
                    $t->leaveType?->code,
                    $t->txn_type,
                    $t->days,
                    $t->balance_after,
                    $t->remarks,
                ])->all(),
            ];
        }

        if ($reportType === 'comp_off') {
            $cos = CompOff::with('employee')->whereIn('employee_id', $empIds)->where('financial_year', $fy)->get();

            return [
                'columns' => ['Emp Code', 'Employee', 'Worked Date', 'Days Earned', 'Expires At', 'Status'],
                'rows' => $cos->map(fn ($c) => [$c->employee?->emp_code, $c->employee?->full_name, $c->worked_date->format('Y-m-d'), $c->days_earned, $c->expires_at?->format('Y-m-d') ?? '-', $c->status])->all(),
            ];
        }

        if ($reportType === 'permission') {
            $perms = PermissionRequest::with('employee')->whereIn('employee_id', $empIds)->whereBetween('permission_date', [$from, $to])->get();

            return [
                'columns' => ['Date', 'Emp Code', 'Employee', 'From', 'To', 'Minutes', 'Status'],
                'rows' => $perms->map(fn ($p) => [$p->permission_date->format('Y-m-d'), $p->employee?->emp_code, $p->employee?->full_name, $p->from_time, $p->to_time, $p->minutes, $p->approval_status])->all(),
            ];
        }

        if ($reportType === 'fwa_status') {
            $fwas = FwaRequest::with(['employee', 'policy'])->whereIn('employee_id', $empIds)->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->get();

            return [
                'columns' => ['Emp Code', 'Employee', 'Policy', 'Start', 'End', 'Status'],
                'rows' => $fwas->map(fn ($f) => [$f->employee?->emp_code, $f->employee?->full_name, $f->policy?->name, $f->start_date->format('Y-m-d'), $f->end_date->format('Y-m-d'), $f->approval_status])->all(),
            ];
        }

        $reqs = LeaveRequest::with(['employee.department', 'leaveType'])
            ->whereIn('employee_id', $empIds)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->when($reportType === 'leave_attachment', fn ($q) => $q->whereNotNull('attachment_path'))
            ->when($reportType === 'leave_taken_history', fn ($q) => $q->where('approval_status', 'approved'))
            ->latest()
            ->take(500)
            ->get();

        return [
            'columns' => ['Emp Code', 'Employee', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Mode', 'Status', 'Attachment'],
            'rows' => $reqs->map(fn ($r) => [
                $r->employee?->emp_code,
                $r->employee?->full_name,
                $r->employee?->department?->name,
                $r->leaveType?->code,
                $r->start_date->format('Y-m-d'),
                $r->end_date->format('Y-m-d'),
                $r->total_days,
                $r->day_mode,
                $r->approval_status,
                $r->attachment_path ? 'Yes' : 'No',
            ])->all(),
        ];
    }
}
