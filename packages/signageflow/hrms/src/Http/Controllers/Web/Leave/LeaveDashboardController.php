<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Leave;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use SignageFlow\Hrms\Models\Category;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\LeaveRequest;
use SignageFlow\Hrms\Models\LeaveType;

class LeaveDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsLeave_list');
    }

    public function index(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $trendRange = $request->input('trend_range', 'monthly'); // weekly | monthly | yearly
        $orgFilters = $request->only(['company_id', 'department_id', 'section_id', 'designation_id', 'category_id', 'extra_classification_id']);

        $empIds = Employee::active()->filterByOrg($orgFilters)->pluck('id');
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $pendingApprovals = LeaveRequest::whereIn('employee_id', $empIds)
            ->whereIn('approval_status', ['pending_l1', 'pending_l2'])
            ->count();

        $monthLeaves = LeaveRequest::with('leaveType:id,code,name')
            ->whereIn('employee_id', $empIds)
            ->where('approval_status', 'approved')
            ->whereDate('start_date', '<=', $monthEnd->toDateString())
            ->whereDate('end_date', '>=', $monthStart->toDateString())
            ->get();

        // Daily bar chart for the selected month
        $dailyCounts = [];
        for ($d = 1; $d <= $monthEnd->day; $d++) {
            $dayStr = Carbon::create($year, $month, $d)->toDateString();
            $dailyCounts[] = [
                'day' => (string) $d,
                'count' => $monthLeaves->filter(fn ($lr) => $lr->start_date->toDateString() <= $dayStr && $lr->end_date->toDateString() >= $dayStr)->count(),
            ];
        }

        // Currently on leave today grouped by leave type
        $today = now()->toDateString();
        $todayLeaves = LeaveRequest::with(['leaveType:id,code,name', 'employee:id,emp_code,full_name,department_id'])
            ->whereIn('employee_id', $empIds)
            ->where('approval_status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get()
            ->groupBy(fn ($lr) => $lr->leaveType?->name ?? 'Other')
            ->map(fn ($group, $typeName) => [
                'leave_type' => $typeName,
                'count' => $group->count(),
                'employees' => $group->map(fn ($lr) => $lr->employee?->full_name)->values(),
            ])->values();

        // Leave Trends KPIs (weekly / monthly / yearly)
        [$trendStart, $trendEnd] = match ($trendRange) {
            'weekly' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'yearly' => [Carbon::create($year, 4, 1)->toDateString(), Carbon::create($year + 1, 3, 31)->toDateString()],
            default => [$monthStart->toDateString(), $monthEnd->toDateString()],
        };

        $trendLeaves = LeaveRequest::whereIn('employee_id', $empIds)
            ->where('approval_status', 'approved')
            ->whereBetween('start_date', [$trendStart, $trendEnd])
            ->get();

        $peakDay = $trendLeaves->groupBy(fn ($lr) => $lr->start_date->toDateString())
            ->map->count()
            ->sortDesc()
            ->keys()
            ->first();

        return Inertia::render('Hrms/Leave/Dashboard', [
            'month' => $month,
            'year' => $year,
            'trendRange' => $trendRange,
            'filters' => $orgFilters,
            'pendingApprovals' => $pendingApprovals,
            'dailyDistribution' => $dailyCounts,
            'employeesByLeaveType' => $todayLeaves,
            'trends' => [
                'total_leaves_taken' => round($trendLeaves->sum('total_days'), 2),
                'peak_day' => $peakDay ?? '-',
                'full_day_count' => $trendLeaves->where('day_mode', 'full')->count(),
                'half_day_count' => $trendLeaves->where('day_mode', '!=', 'full')->count(),
            ],
            'orgOptions' => [
                'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'leaveTypes' => LeaveType::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            ],
        ]);
    }
}
