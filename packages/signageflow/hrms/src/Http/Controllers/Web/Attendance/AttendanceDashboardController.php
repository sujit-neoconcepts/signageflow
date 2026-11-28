<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Attendance;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SignageFlow\Hrms\Models\AttendanceDay;
use SignageFlow\Hrms\Models\AttendanceRequest;
use SignageFlow\Hrms\Models\Category;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Designation;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\ExtraClassification;
use SignageFlow\Hrms\Models\Section;
use SignageFlow\Hrms\Models\Shift;

class AttendanceDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsDashboard_view');
    }

    public function index(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()))->toDateString();
        $orgFilters = $request->only(['company_id', 'department_id', 'section_id', 'designation_id', 'category_id', 'extra_classification_id']);

        $activeEmployees = Employee::active()->filterByOrg($orgFilters)->with(['department:id,name', 'designation:id,name', 'defaultShift:id,code,name'])->get();
        $empIds = $activeEmployees->pluck('id');
        $headcount = $activeEmployees->count();

        $days = AttendanceDay::whereIn('employee_id', $empIds)->whereDate('attendance_date', $date)->get()->keyBy('employee_id');

        $presentCount = $days->whereIn('status', ['present', 'half_day', 'fwa'])->count();
        $absentCount = max(0, $headcount - $presentCount - $days->whereIn('status', ['on_leave', 'holiday', 'week_off'])->count());
        $onLeaveCount = $days->where('status', 'on_leave')->count();
        $lateCount = $days->where('is_late', true)->count();
        $earlyOutCount = $days->where('is_early_out', true)->count();
        $onTimeCount = max(0, $presentCount - $lateCount);
        $pendingApprovals = AttendanceRequest::whereIn('employee_id', $empIds)->whereIn('approval_status', ['pending_l1', 'pending_l2'])->count();

        // Absent today roster
        $absentRoster = $activeEmployees->filter(function (Employee $emp) use ($days) {
            $d = $days->get($emp->id);

            return ! $d || $d->status === 'absent';
        })->map(fn (Employee $emp) => [
            'id' => $emp->id,
            'emp_code' => $emp->emp_code,
            'full_name' => $emp->full_name,
            'department' => $emp->department?->name,
            'designation' => $emp->designation?->name,
        ])->values();

        // Daily Shifts Stacked Bar Chart
        $shifts = Shift::where('is_active', true)->orderBy('code')->get();
        $shiftChart = $shifts->map(function (Shift $shift) use ($activeEmployees, $days) {
            $shiftEmpIds = $activeEmployees->where('default_shift_id', $shift->id)->pluck('id');
            $shiftDays = $days->only($shiftEmpIds->all());

            return [
                'label' => $shift->code.': '.substr($shift->start_time, 0, 5).'-'.substr($shift->end_time, 0, 5),
                'present' => $shiftDays->whereIn('status', ['present', 'half_day', 'fwa'])->count(),
                'absent' => max(0, $shiftEmpIds->count() - $shiftDays->whereIn('status', ['present', 'half_day', 'fwa', 'on_leave', 'holiday', 'week_off'])->count()),
                'late' => $shiftDays->where('is_late', true)->count(),
                'early_out' => $shiftDays->where('is_early_out', true)->count(),
            ];
        })->values();

        // Rolling 30-day Late Comers with trend arrows
        $now30Start = Carbon::parse($date)->subDays(29)->toDateString();
        $prev30Start = Carbon::parse($date)->subDays(59)->toDateString();
        $prev30End = Carbon::parse($date)->subDays(30)->toDateString();

        $lateRecent = AttendanceDay::whereIn('employee_id', $empIds)
            ->whereBetween('attendance_date', [$now30Start, $date])
            ->where('is_late', true)
            ->selectRaw('employee_id, COUNT(*) as late_count')
            ->groupBy('employee_id')
            ->orderByDesc('late_count')
            ->take(10)
            ->get();

        $latePrevMap = AttendanceDay::whereIn('employee_id', $lateRecent->pluck('employee_id'))
            ->whereBetween('attendance_date', [$prev30Start, $prev30End])
            ->where('is_late', true)
            ->selectRaw('employee_id, COUNT(*) as late_count')
            ->groupBy('employee_id')
            ->pluck('late_count', 'employee_id');

        $empMap = $activeEmployees->keyBy('id');
        $lateComers = $lateRecent->map(function ($row) use ($empMap, $latePrevMap) {
            $prev = (int) ($latePrevMap[$row->employee_id] ?? 0);
            $emp = $empMap->get($row->employee_id);

            return [
                'emp_code' => $emp?->emp_code,
                'full_name' => $emp?->full_name,
                'department' => $emp?->department?->name,
                'count_30d' => (int) $row->late_count,
                'prev_30d' => $prev,
                'trend' => $row->late_count > $prev ? 'up' : ($row->late_count < $prev ? 'down' : 'flat'),
            ];
        })->values();

        $genderBreakdown = [
            'male' => $activeEmployees->where('gender', 'male')->count(),
            'female' => $activeEmployees->where('gender', 'female')->count(),
            'other' => $activeEmployees->whereNotIn('gender', ['male', 'female'])->count(),
        ];

        return Inertia::render('Hrms/Attendance/Dashboard', [
            'date' => $date,
            'filters' => $orgFilters,
            'kpis' => [
                'headcount' => $headcount,
                'present' => $presentCount,
                'attendance_rate' => $headcount > 0 ? round(($presentCount / $headcount) * 100, 1) : 0,
                'on_time' => $onTimeCount,
                'absent' => $absentCount,
                'on_leave' => $onLeaveCount,
                'late' => $lateCount,
                'early_out' => $earlyOutCount,
                'pending_approvals' => $pendingApprovals,
            ],
            'absentRoster' => $absentRoster,
            'shiftChart' => $shiftChart,
            'lateComers' => $lateComers,
            'genderBreakdown' => $genderBreakdown,
            'orgOptions' => [
                'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'sections' => Section::where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'designations' => Designation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'extraClassifications' => ExtraClassification::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function exportDaily(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()))->toDateString();
        $orgFilters = $request->only(['company_id', 'department_id', 'section_id', 'designation_id', 'category_id', 'extra_classification_id']);

        $employees = Employee::active()->filterByOrg($orgFilters)->with(['department', 'designation'])->orderBy('emp_code')->get();
        $days = AttendanceDay::whereIn('employee_id', $employees->pluck('id'))->whereDate('attendance_date', $date)->get()->keyBy('employee_id');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Date', 'Emp Code', 'Name', 'Department', 'Status', 'In', 'Out', 'Worked Mins', 'Late Mins', 'Early Out Mins', 'OT Mins'], null, 'A1');

        $row = 2;
        foreach ($employees as $emp) {
            $d = $days->get($emp->id);
            $sheet->fromArray([
                $date,
                $emp->emp_code,
                $emp->full_name,
                $emp->department?->name,
                $d?->status ?? 'absent',
                $d?->first_in_at?->format('H:i'),
                $d?->last_out_at?->format('H:i'),
                $d?->worked_minutes ?? 0,
                $d?->late_minutes ?? 0,
                $d?->early_out_minutes ?? 0,
                $d?->overtime_minutes ?? 0,
            ], null, 'A'.$row++);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "attendance-kpi-{$date}.xlsx");
    }
}
