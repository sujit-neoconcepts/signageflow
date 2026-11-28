<?php

namespace SignageFlow\Hrms\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use SignageFlow\Hrms\Models\AttendanceAllowance;
use SignageFlow\Hrms\Models\AttendanceAllowanceEntry;
use SignageFlow\Hrms\Models\AttendanceDay;
use SignageFlow\Hrms\Models\Employee;

class AttendanceAllowanceService
{
    public function evaluateMonth(int $year, int $month, array $orgFilters = []): Collection
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $rules = AttendanceAllowance::where('is_active', true)->get();
        $employees = Employee::active()->filterByOrg($orgFilters)->get();

        $entries = collect();

        foreach ($rules as $rule) {
            foreach ($employees as $employee) {
                $days = AttendanceDay::where('employee_id', $employee->id)
                    ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
                    ->get();

                $absences = $days->where('status', 'absent')->count();
                $lates = $days->where('is_late', true)->count();
                $presentDays = $days->whereIn('status', ['present', 'fwa'])->count() + ($days->where('status', 'half_day')->count() * 0.5);

                $eligible = $absences <= $rule->max_absences_allowed
                    && $lates <= $rule->max_lates_allowed
                    && $presentDays >= $rule->min_present_days;

                $entry = AttendanceAllowanceEntry::updateOrCreate(
                    [
                        'attendance_allowance_id' => $rule->id,
                        'employee_id' => $employee->id,
                        'period_year' => $year,
                        'period_month' => $month,
                    ],
                    [
                        'computed_amount' => $eligible ? $rule->amount : 0,
                        'is_eligible' => $eligible,
                    ]
                );

                $entries->push($entry);
            }
        }

        return $entries;
    }
}
