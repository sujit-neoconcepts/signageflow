<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Attendance;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SignageFlow\Hrms\Models\AttendanceDay;
use SignageFlow\Hrms\Models\AttendancePunch;
use SignageFlow\Hrms\Models\AttendanceRequest;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\WorkLocation;

class AttendanceReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsAttendanceReport_list')->only(['index']);
        $this->middleware('can:hrmsAttendanceReport_export')->only(['export']);
    }

    public function index(Request $request)
    {
        $reportType = $request->input('report', 'daily_attendance');
        $from = Carbon::parse($request->input('from_date', now()->startOfMonth()->toDateString()))->toDateString();
        $to = Carbon::parse($request->input('to_date', now()->toDateString()))->toDateString();
        $orgFilters = $request->only(['company_id', 'department_id', 'section_id', 'designation_id', 'category_id', 'extra_classification_id']);

        $data = $this->buildReportData($reportType, $from, $to, $orgFilters, $request->input('employee_id'));

        return Inertia::render('Hrms/Attendance/Reports', [
            'reportType' => $reportType,
            'fromDate' => $from,
            'toDate' => $to,
            'filters' => $orgFilters,
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'emp_code', 'full_name']),
        ]);
    }

    public function export(Request $request)
    {
        $reportType = $request->input('report', 'daily_attendance');
        $from = Carbon::parse($request->input('from_date', now()->startOfMonth()->toDateString()))->toDateString();
        $to = Carbon::parse($request->input('to_date', now()->toDateString()))->toDateString();
        $orgFilters = $request->only(['company_id', 'department_id', 'section_id', 'designation_id', 'category_id', 'extra_classification_id']);

        $data = $this->buildReportData($reportType, $from, $to, $orgFilters, $request->input('employee_id'));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($data['columns'], null, 'A1');

        $rowNum = 2;
        foreach ($data['rows'] as $row) {
            $sheet->fromArray(array_values($row), null, 'A'.$rowNum++);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "hrms-{$reportType}-{$from}-to-{$to}.xlsx");
    }

    protected function buildReportData(string $reportType, string $from, string $to, array $orgFilters, $employeeId = null): array
    {
        $empIds = Employee::active()
            ->filterByOrg($orgFilters)
            ->when($employeeId, fn ($q) => $q->where('id', $employeeId))
            ->pluck('id');

        if ($reportType === 'clocking_data' || $reportType === 'mobile_daily' || $reportType === 'mobile_reports') {
            $punches = AttendancePunch::with(['employee:id,emp_code,full_name,department_id', 'workLocation:id,name'])
                ->whereIn('employee_id', $empIds)
                ->whereBetween('punch_date', [$from, $to])
                ->when(in_array($reportType, ['mobile_daily', 'mobile_reports'], true), fn ($q) => $q->where('channel', 'mobile'))
                ->latest('punched_at')
                ->take(500)
                ->get();

            return [
                'columns' => ['Date', 'Time', 'Emp Code', 'Employee', 'Channel', 'Direction', 'Location', 'Distance (m)', 'Geofence Flag'],
                'rows' => $punches->map(fn ($p) => [
                    $p->punch_date->format('Y-m-d'),
                    $p->punched_at->format('H:i:s'),
                    $p->employee?->emp_code,
                    $p->employee?->full_name,
                    $p->channel,
                    $p->direction,
                    $p->workLocation?->name ?? '-',
                    $p->distance_meters ?? '-',
                    $p->is_out_of_geofence ? 'Flagged' : 'OK',
                ])->all(),
            ];
        }

        if ($reportType === 'regularization') {
            $reqs = AttendanceRequest::with(['employee:id,emp_code,full_name'])
                ->whereIn('employee_id', $empIds)
                ->whereBetween('attendance_date', [$from, $to])
                ->latest()
                ->get();

            return [
                'columns' => ['Date', 'Emp Code', 'Employee', 'Type', 'Reason', 'Status'],
                'rows' => $reqs->map(fn ($r) => [
                    $r->attendance_date->format('Y-m-d'),
                    $r->employee?->emp_code,
                    $r->employee?->full_name,
                    $r->request_type,
                    $r->reason,
                    $r->approval_status,
                ])->all(),
            ];
        }

        if ($reportType === 'work_location' || $reportType === 'assigned_work_location') {
            $locs = WorkLocation::withCount('employees')->get();

            return [
                'columns' => ['Code', 'Name', 'Geofence Mode', 'Radius (m)', 'Assigned Staff'],
                'rows' => $locs->map(fn ($l) => [$l->code, $l->name, $l->geofence_mode, $l->radius_meters, $l->employees_count])->all(),
            ];
        }

        if (in_array($reportType, ['consolidated_monthly', 'department_attendance', 'working_hours_summary', 'monthly_ot'], true)) {
            $days = AttendanceDay::with('employee.department')
                ->whereIn('employee_id', $empIds)
                ->whereBetween('attendance_date', [$from, $to])
                ->get()
                ->groupBy('employee_id');

            return [
                'columns' => ['Emp Code', 'Employee', 'Department', 'Present', 'Absent', 'Half Day', 'Leave', 'Late Days', 'Worked Hrs', 'Approved OT Hrs'],
                'rows' => $days->map(function ($group) {
                    $emp = $group->first()->employee;

                    return [
                        $emp?->emp_code,
                        $emp?->full_name,
                        $emp?->department?->name,
                        $group->whereIn('status', ['present', 'fwa'])->count(),
                        $group->where('status', 'absent')->count(),
                        $group->where('status', 'half_day')->count(),
                        $group->where('status', 'on_leave')->count(),
                        $group->where('is_late', true)->count(),
                        round($group->sum('worked_minutes') / 60, 2),
                        round($group->sum('approved_overtime_minutes') / 60, 2),
                    ];
                })->values()->all(),
            ];
        }

        // Default: daily_attendance, muster, individual, day_wise_ot, break_reports, attendance_problem
        $days = AttendanceDay::with(['employee.department', 'shift'])
            ->whereIn('employee_id', $empIds)
            ->whereBetween('attendance_date', [$from, $to])
            ->when($reportType === 'day_wise_ot', fn ($q) => $q->where('overtime_minutes', '>', 0))
            ->when($reportType === 'attendance_problem', fn ($q) => $q->where(fn ($sub) => $sub->where('is_late', true)->orWhere('is_early_out', true)->orWhere('is_geofence_flagged', true)->orWhere('status', 'absent')))
            ->orderByDesc('attendance_date')
            ->take(500)
            ->get();

        return [
            'columns' => ['Date', 'Emp Code', 'Employee', 'Department', 'Shift', 'Status', 'In', 'Out', 'Worked (m)', 'Break (m)', 'Late (m)', 'Early (m)', 'OT (m)'],
            'rows' => $days->map(fn ($d) => [
                $d->attendance_date->format('Y-m-d'),
                $d->employee?->emp_code,
                $d->employee?->full_name,
                $d->employee?->department?->name,
                $d->shift?->code ?? '-',
                $d->status,
                $d->first_in_at?->format('H:i') ?? '-',
                $d->last_out_at?->format('H:i') ?? '-',
                $d->worked_minutes,
                $d->break_minutes,
                $d->late_minutes,
                $d->early_out_minutes,
                $d->overtime_minutes,
            ])->all(),
        ];
    }
}
