<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Attendance;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\AttendanceAllowanceEntry;
use SignageFlow\Hrms\Models\AttendanceDay;
use SignageFlow\Hrms\Models\AttendancePunch;
use SignageFlow\Hrms\Models\AttendanceRequest;
use SignageFlow\Hrms\Models\BreakAdjustment;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\OvertimeApproval;
use SignageFlow\Hrms\Services\ApprovalRoutingService;
use SignageFlow\Hrms\Services\AttendanceAllowanceService;
use SignageFlow\Hrms\Services\AttendanceCalculationService;
use SignageFlow\Hrms\Services\HrmsNotificationService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class AttendanceAdjustmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsAttendance_list')->only(['index']);
        $this->middleware('can:hrmsAttendance_edit')->only(['applyAdjustment', 'adjustBreak']);
        $this->middleware('can:hrmsAttendance_approve')->only(['actOnRequest', 'actOnOt', 'computeAllowances', 'approveAllowance']);
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'adjust');
        $date = $request->input('date', now()->toDateString());
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $perPage = (int) ($request->query('perPage') ?? 15);

        [$records, $tableBuilder] = match ($tab) {
            'regularization' => $this->queryRegularizations($perPage),
            'ot' => $this->queryOt($perPage),
            'allowances' => $this->queryAllowances($perPage, $year, $month),
            default => $this->queryAttendanceDays($perPage, $date),
        };

        return Inertia::render('Hrms/Attendance/AdjustmentsAndApprovals', [
            'tab' => $tab,
            'date' => $date,
            'month' => $month,
            'year' => $year,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'emp_code', 'full_name', 'department_id']),
            'records' => $records,
            'resourceNeo' => [
                'resourceName' => 'hrms.attendance.adjustments',
                'resourceTitle' => 'Attendance Adjustments & Approvals',
                'actions' => ['r', 'u'],
            ],
        ])->table($tableBuilder);
    }

    protected function queryAttendanceDays(int $perPage, string $date): array
    {
        $query = AttendanceDay::query()
            ->leftJoin('hrms_employees as emp', 'hrms_attendance_days.employee_id', '=', 'emp.id')
            ->leftJoin('hrms_shifts as sh', 'hrms_attendance_days.shift_id', '=', 'sh.id')
            ->whereDate('hrms_attendance_days.attendance_date', $date)
            ->select([
                'hrms_attendance_days.*',
                'emp.emp_code as emp_code',
                'emp.full_name as employee_name',
                'sh.code as shift_code',
            ]);

        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('emp.emp_code', 'LIKE', "%{$v}%")->orWhere('emp.full_name', 'LIKE', "%{$v}%"));
            });
        });

        $records = QueryBuilder::for($query)
            ->defaultSort('emp.emp_code')
            ->allowedSorts([
                AllowedSort::field('emp_code', 'emp.emp_code'),
                AllowedSort::field('employee_name', 'emp.full_name'),
                'status',
                'worked_minutes',
                'late_minutes',
            ])
            ->allowedFilters([
                AllowedFilter::exact('status', 'hrms_attendance_days.status'),
                AllowedFilter::exact('department_id', 'emp.department_id'),
                $global,
            ])
            ->paginate($perPage)
            ->withQueryString();

        $departments = Department::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray();

        return [$records, function (InertiaTable $table) use ($departments) {
            $table->withGlobalSearch()
                ->column('emp_code', 'Emp Code', searchable: true, sortable: true)
                ->column('employee_name', 'Employee', searchable: true, sortable: true)
                ->column('shift_code', 'Shift', searchable: false, sortable: false)
                ->column('status', 'Status', searchable: false, sortable: true)
                ->column('first_in_at', 'In', searchable: false, sortable: false)
                ->column('last_out_at', 'Out', searchable: false, sortable: false)
                ->column('worked_minutes', 'Worked (m)', searchable: false, sortable: true)
                ->column('break_minutes', 'Break (m)', searchable: false, sortable: false)
                ->column(label: 'Actions')
                ->selectFilter(key: 'department_id', label: 'Department', options: $departments, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'status', label: 'Status', options: [
                    'present' => 'Present',
                    'absent' => 'Absent',
                    'half_day' => 'Half Day',
                    'on_leave' => 'On Leave',
                    'fwa' => 'FWA',
                ], noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryRegularizations(int $perPage): array
    {
        $query = AttendanceRequest::query()
            ->leftJoin('hrms_employees as emp', 'hrms_attendance_requests.employee_id', '=', 'emp.id')
            ->select([
                'hrms_attendance_requests.*',
                'emp.emp_code as emp_code',
                'emp.full_name as employee_name',
            ]);

        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('emp.emp_code', 'LIKE', "%{$v}%")->orWhere('emp.full_name', 'LIKE', "%{$v}%")->orWhere('hrms_attendance_requests.reason', 'LIKE', "%{$v}%"));
            });
        });

        $records = QueryBuilder::for($query)
            ->defaultSort('-hrms_attendance_requests.created_at')
            ->allowedSorts([
                'attendance_date',
                AllowedSort::field('employee_name', 'emp.full_name'),
                'request_type',
                'approval_status',
            ])
            ->allowedFilters([
                AllowedFilter::exact('approval_status', 'hrms_attendance_requests.approval_status'),
                AllowedFilter::exact('request_type', 'hrms_attendance_requests.request_type'),
                $global,
            ])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('attendance_date', 'Date', searchable: false, sortable: true)
                ->column('emp_code', 'Emp Code', searchable: true, sortable: false)
                ->column('employee_name', 'Employee', searchable: true, sortable: true)
                ->column('request_type', 'Type', searchable: false, sortable: true)
                ->column('reason', 'Reason', searchable: true, sortable: false)
                ->column('approval_status', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->selectFilter(key: 'approval_status', label: 'Status', options: [
                    'pending_l1' => 'Pending L1',
                    'pending_l2' => 'Pending L2',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ], noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryOt(int $perPage): array
    {
        $query = OvertimeApproval::query()
            ->leftJoin('hrms_employees as emp', 'hrms_overtime_approvals.employee_id', '=', 'emp.id')
            ->select([
                'hrms_overtime_approvals.*',
                'emp.emp_code as emp_code',
                'emp.full_name as employee_name',
            ]);

        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('emp.emp_code', 'LIKE', "%{$v}%")->orWhere('emp.full_name', 'LIKE', "%{$v}%"));
            });
        });

        $records = QueryBuilder::for($query)
            ->defaultSort('-hrms_overtime_approvals.attendance_date')
            ->allowedSorts([
                'attendance_date',
                AllowedSort::field('employee_name', 'emp.full_name'),
                'computed_ot_minutes',
                'approval_status',
            ])
            ->allowedFilters([
                AllowedFilter::exact('approval_status', 'hrms_overtime_approvals.approval_status'),
                $global,
            ])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('attendance_date', 'Date', searchable: false, sortable: true)
                ->column('emp_code', 'Emp Code', searchable: true, sortable: false)
                ->column('employee_name', 'Employee', searchable: true, sortable: true)
                ->column('computed_ot_minutes', 'Computed OT (m)', searchable: false, sortable: true)
                ->column('approved_ot_minutes', 'Approved OT (m)', searchable: false, sortable: false)
                ->column('approval_status', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->selectFilter(key: 'approval_status', label: 'Status', options: [
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ], noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryAllowances(int $perPage, int $year, int $month): array
    {
        $query = AttendanceAllowanceEntry::query()
            ->leftJoin('hrms_employees as emp', 'hrms_attendance_allowance_entries.employee_id', '=', 'emp.id')
            ->leftJoin('hrms_attendance_allowances as alw', 'hrms_attendance_allowance_entries.attendance_allowance_id', '=', 'alw.id')
            ->where('hrms_attendance_allowance_entries.period_year', $year)
            ->where('hrms_attendance_allowance_entries.period_month', $month)
            ->select([
                'hrms_attendance_allowance_entries.*',
                'emp.emp_code as emp_code',
                'emp.full_name as employee_name',
                'alw.name as allowance_name',
            ]);

        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('emp.emp_code', 'LIKE', "%{$v}%")->orWhere('emp.full_name', 'LIKE', "%{$v}%"));
            });
        });

        $records = QueryBuilder::for($query)
            ->defaultSort('emp.emp_code')
            ->allowedSorts([
                AllowedSort::field('employee_name', 'emp.full_name'),
                'computed_amount',
                'is_eligible',
                'status',
            ])
            ->allowedFilters([
                AllowedFilter::exact('status', 'hrms_attendance_allowance_entries.status'),
                AllowedFilter::exact('is_eligible', 'hrms_attendance_allowance_entries.is_eligible'),
                $global,
            ])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('emp_code', 'Emp Code', searchable: true, sortable: false)
                ->column('employee_name', 'Employee', searchable: true, sortable: true)
                ->column('allowance_name', 'Rule', searchable: false, sortable: false)
                ->column('is_eligible', 'Eligible', searchable: false, sortable: true)
                ->column('computed_amount', 'Amount', searchable: false, sortable: true)
                ->column('status', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->selectFilter(key: 'status', label: 'Status', options: [
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ], noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    public function applyAdjustment(Request $request, AttendanceCalculationService $service)
    {
        $data = $request->validate([
            'mode' => 'required|in:individual,department,filters,bulk',
            'attendance_date' => 'required|date',
            'employee_id' => 'nullable|exists:hrms_employees,id',
            'department_id' => 'nullable|exists:hrms_departments,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:hrms_employees,id',
            'in_time' => 'nullable|date_format:H:i',
            'out_time' => 'nullable|date_format:H:i',
            'status_override' => 'nullable|in:present,absent,half_day,on_leave,fwa,holiday,week_off',
            'notes' => 'nullable|string|max:500',
        ]);

        $employees = match ($data['mode']) {
            'individual' => Employee::where('id', $data['employee_id'])->get(),
            'department' => Employee::active()->where('department_id', $data['department_id'])->get(),
            'bulk', 'filters' => Employee::active()->when(! empty($data['employee_ids']), fn ($q) => $q->whereIn('id', $data['employee_ids']))
                ->when(! empty($data['department_id']), fn ($q) => $q->where('department_id', $data['department_id']))
                ->get(),
        };

        $dateStr = Carbon::parse($data['attendance_date'])->toDateString();

        DB::transaction(function () use ($employees, $data, $dateStr, $service) {
            foreach ($employees as $emp) {
                if (! empty($data['in_time'])) {
                    AttendancePunch::create([
                        'employee_id' => $emp->id,
                        'punched_at' => Carbon::parse($dateStr.' '.$data['in_time']),
                        'punch_date' => $dateStr,
                        'direction' => 'in',
                        'channel' => 'manual',
                        'status' => 'valid',
                        'created_by' => auth()->id(),
                    ]);
                }
                if (! empty($data['out_time'])) {
                    AttendancePunch::create([
                        'employee_id' => $emp->id,
                        'punched_at' => Carbon::parse($dateStr.' '.$data['out_time']),
                        'punch_date' => $dateStr,
                        'direction' => 'out',
                        'channel' => 'manual',
                        'status' => 'valid',
                        'created_by' => auth()->id(),
                    ]);
                }

                $day = $service->recalculateDay($emp, $dateStr);
                if (! empty($data['status_override']) && ! $day->is_locked_by_payroll) {
                    $day->update(['status' => $data['status_override'], 'notes' => $data['notes'] ?? $day->notes]);
                }
            }
        });

        return back()->with(['message' => 'Adjustment applied for '.$employees->count().' employees.', 'msg_type' => 'success']);
    }

    public function actOnRequest(
        Request $request,
        AttendanceRequest $attendanceRequest,
        ApprovalRoutingService $routing,
        AttendanceCalculationService $calculation,
        HrmsNotificationService $notifier
    ) {
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $authCheck = $routing->canActOn(auth()->user(), $attendanceRequest->currentApprover, $attendanceRequest->employee, 'attendance');
        if (! $authCheck['authorized']) {
            abort(403, 'You are not authorized to act on this attendance request.');
        }

        DB::transaction(function () use ($data, $attendanceRequest, $authCheck, $routing, $calculation, $notifier) {
            $emp = $attendanceRequest->employee;
            $dateStr = $attendanceRequest->attendance_date->toDateString();

            if ($data['action'] === 'reject') {
                $attendanceRequest->update([
                    'approval_status' => 'rejected',
                    'rejection_reason' => $data['rejection_reason'] ?? 'Rejected',
                    'acted_via_backup' => $authCheck['via_backup'],
                ]);
                $notifier->dispatch('attendance_regularized', $emp, ['date' => $dateStr, 'status' => 'Rejected']);

                return;
            }

            if ($attendanceRequest->current_level === 1 && $emp->l2Approver) {
                $next = $routing->resolveApprover($emp, 2, 'attendance');
                $attendanceRequest->update([
                    'approval_status' => 'pending_l2',
                    'current_level' => 2,
                    'current_approver_id' => $next['approver']?->id,
                    'l1_acted_by' => auth()->id(),
                    'l1_acted_at' => now(),
                    'acted_via_backup' => $authCheck['via_backup'],
                ]);

                return;
            }

            $attendanceRequest->update([
                'approval_status' => 'approved',
                'l2_acted_by' => auth()->id(),
                'l2_acted_at' => now(),
                'acted_via_backup' => $authCheck['via_backup'],
            ]);

            if ($attendanceRequest->requested_in_at) {
                AttendancePunch::create([
                    'employee_id' => $emp->id,
                    'punched_at' => $attendanceRequest->requested_in_at,
                    'punch_date' => $dateStr,
                    'direction' => 'in',
                    'channel' => 'manual',
                    'status' => 'valid',
                    'created_by' => auth()->id(),
                ]);
            }
            if ($attendanceRequest->requested_out_at) {
                AttendancePunch::create([
                    'employee_id' => $emp->id,
                    'punched_at' => $attendanceRequest->requested_out_at,
                    'punch_date' => $dateStr,
                    'direction' => 'out',
                    'channel' => 'manual',
                    'status' => 'valid',
                    'created_by' => auth()->id(),
                ]);
            }

            $calculation->recalculateDay($emp, $dateStr);
            $notifier->dispatch('attendance_regularized', $emp, ['date' => $dateStr, 'status' => 'Approved']);
        });

        return back()->with(['message' => 'Attendance request updated.', 'msg_type' => 'success']);
    }

    public function actOnOt(Request $request, OvertimeApproval $otApproval)
    {
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'approved_ot_minutes' => 'nullable|integer|min:0|max:960',
            'remarks' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data, $otApproval) {
            $approvedMins = $data['action'] === 'approve'
                ? ($data['approved_ot_minutes'] ?? $otApproval->computed_ot_minutes)
                : 0;

            $otApproval->update([
                'approval_status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
                'approved_ot_minutes' => $approvedMins,
                'acted_by' => auth()->id(),
                'acted_at' => now(),
                'remarks' => $data['remarks'] ?? null,
            ]);

            $otApproval->attendanceDay?->update(['approved_overtime_minutes' => $approvedMins]);
        });

        return back()->with(['message' => 'Overtime decision saved.', 'msg_type' => 'success']);
    }

    public function adjustBreak(Request $request, AttendanceDay $attendanceDay)
    {
        $data = $request->validate([
            'adjusted_break_minutes' => 'required|integer|min:0|max:360',
            'reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($data, $attendanceDay) {
            BreakAdjustment::create([
                'attendance_day_id' => $attendanceDay->id,
                'employee_id' => $attendanceDay->employee_id,
                'previous_break_minutes' => $attendanceDay->break_minutes,
                'adjusted_break_minutes' => $data['adjusted_break_minutes'],
                'reason' => $data['reason'],
                'adjusted_by' => auth()->id(),
            ]);

            $diff = $data['adjusted_break_minutes'] - $attendanceDay->break_minutes;
            $attendanceDay->update([
                'break_minutes' => $data['adjusted_break_minutes'],
                'worked_minutes' => max(0, $attendanceDay->worked_minutes - $diff),
            ]);
        });

        return back()->with(['message' => 'Break minutes adjusted.', 'msg_type' => 'success']);
    }

    public function computeAllowances(Request $request, AttendanceAllowanceService $service)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $entries = $service->evaluateMonth($data['year'], $data['month']);

        return back()->with(['message' => "Evaluated {$entries->count()} attendance allowance records.", 'msg_type' => 'success']);
    }

    public function approveAllowance(Request $request, AttendanceAllowanceEntry $entry)
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected']);
        $entry->update(['status' => $data['status'], 'approved_by' => auth()->id()]);

        return back()->with(['message' => 'Attendance allowance status updated.', 'msg_type' => 'success']);
    }
}
