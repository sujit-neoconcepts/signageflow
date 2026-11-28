<?php

namespace SignageFlow\Hrms\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use SignageFlow\Hrms\Models\AttendanceDay;
use SignageFlow\Hrms\Models\AttendancePunch;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\EmployeeShift;
use SignageFlow\Hrms\Models\FwaRequest;
use SignageFlow\Hrms\Models\Holiday;
use SignageFlow\Hrms\Models\LeaveRequest;
use SignageFlow\Hrms\Models\OvertimeApproval;
use SignageFlow\Hrms\Models\Shift;

class AttendanceCalculationService
{
    public function resolveShiftForDate(Employee $employee, Carbon $date): ?Shift
    {
        $dateStr = $date->toDateString();

        $roster = EmployeeShift::query()
            ->where('employee_id', $employee->id)
            ->whereDate('effective_from', '<=', $dateStr)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $dateStr);
            })
            ->with('shift')
            ->latest('effective_from')
            ->first();

        return $roster?->shift ?? $employee->defaultShift;
    }

    public function recalculateDay(Employee $employee, Carbon|string $date): AttendanceDay
    {
        $carbonDate = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
        $dateStr = $carbonDate->toDateString();

        return DB::transaction(function () use ($employee, $carbonDate, $dateStr) {
            $existing = AttendanceDay::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $dateStr)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->is_locked_by_payroll) {
                return $existing;
            }

            $shift = $this->resolveShiftForDate($employee, $carbonDate);

            // Check punches on this date
            $punches = AttendancePunch::query()
                ->where('employee_id', $employee->id)
                ->whereDate('punch_date', $dateStr)
                ->where('status', '!=', 'rejected')
                ->orderBy('punched_at')
                ->get();

            // Check approved leave
            $approvedLeave = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('approval_status', 'approved')
                ->whereDate('start_date', '<=', $dateStr)
                ->whereDate('end_date', '>=', $dateStr)
                ->with('leaveType')
                ->first();

            // Check approved FWA
            $approvedFwa = FwaRequest::query()
                ->where('employee_id', $employee->id)
                ->where('approval_status', 'approved')
                ->whereDate('start_date', '<=', $dateStr)
                ->whereDate('end_date', '>=', $dateStr)
                ->first();

            // Check public holiday / rest day
            $isHoliday = Holiday::query()
                ->where('is_active', true)
                ->whereIn('holiday_type', ['public_holiday', 'rest_day'])
                ->whereDate('start_date', '<=', $dateStr)
                ->whereDate('end_date', '>=', $dateStr)
                ->where(function ($q) use ($employee) {
                    $q->whereNull('company_id')->orWhere('company_id', $employee->company_id);
                })
                ->where(function ($q) use ($employee) {
                    $q->whereNull('department_id')->orWhere('department_id', $employee->department_id);
                })
                ->where(function ($q) use ($employee) {
                    $q->whereNull('work_location_id')->orWhere('work_location_id', $employee->work_location_id);
                })
                ->exists();

            $weekOffDays = $shift?->week_off_days ?? [0]; // 0 = Sunday
            $isWeekOff = in_array($carbonDate->dayOfWeek, $weekOffDays, true);

            $firstIn = null;
            $lastOut = null;
            $breakMinutes = 0;
            $workedMinutes = 0;
            $lateMinutes = 0;
            $earlyOutMinutes = 0;
            $overtimeMinutes = 0;
            $isGeofenceFlagged = $punches->contains('is_out_of_geofence', true);

            if ($punches->isNotEmpty()) {
                $inPunches = $punches->whereIn('direction', ['in', 'auto']);
                $outPunches = $punches->whereIn('direction', ['out', 'auto']);

                $firstIn = $inPunches->first()?->punched_at ?? $punches->first()->punched_at;
                if ($punches->count() > 1) {
                    $lastOut = $outPunches->last()?->punched_at ?? $punches->last()->punched_at;
                }

                // Explicit break pairs if logged
                $breakStart = null;
                foreach ($punches as $punch) {
                    if ($punch->direction === 'break_start') {
                        $breakStart = $punch->punched_at;
                    } elseif ($punch->direction === 'break_end' && $breakStart) {
                        $breakMinutes += (int) round($breakStart->diffInMinutes($punch->punched_at));
                        $breakStart = null;
                    }
                }

                if ($firstIn && $lastOut && $lastOut->greaterThan($firstIn)) {
                    $grossMinutes = (int) round($firstIn->diffInMinutes($lastOut));
                    $effectiveBreak = $breakMinutes > 0
                        ? $breakMinutes
                        : ($grossMinutes > ($shift?->half_day_minutes ?? 240) ? ($shift?->break_minutes ?? 0) : 0);
                    $breakMinutes = $effectiveBreak;
                    $workedMinutes = max(0, $grossMinutes - $effectiveBreak);
                }

                if ($shift && $firstIn) {
                    $shiftStart = Carbon::parse($dateStr.' '.$shift->start_time);
                    $shiftEnd = Carbon::parse($dateStr.' '.$shift->end_time);
                    if ($shift->is_overnight && $shiftEnd->lessThanOrEqualTo($shiftStart)) {
                        $shiftEnd->addDay();
                    }

                    $graceInEnd = $shiftStart->copy()->addMinutes($shift->grace_in_minutes);
                    if ($firstIn->greaterThan($graceInEnd)) {
                        $lateMinutes = (int) $shiftStart->diffInMinutes($firstIn);
                    }

                    if ($lastOut) {
                        $graceOutStart = $shiftEnd->copy()->subMinutes($shift->grace_out_minutes);
                        if ($lastOut->lessThan($graceOutStart)) {
                            $earlyOutMinutes = (int) $lastOut->diffInMinutes($shiftEnd);
                        }
                    }

                    if ($shift->ot_eligible && $workedMinutes > ($shift->full_day_minutes + $shift->ot_min_threshold_minutes)) {
                        $overtimeMinutes = $workedMinutes - $shift->full_day_minutes;
                    }
                }
            }

            // Determine final status
            $leaveCode = null;
            if ($approvedLeave && $approvedLeave->day_mode === 'full') {
                $status = 'on_leave';
                $leaveCode = $approvedLeave->leaveType?->code;
            } elseif ($approvedFwa && $punches->isEmpty()) {
                $status = 'fwa';
            } elseif ($punches->isNotEmpty()) {
                $halfMin = $shift?->half_day_minutes ?? (int) config('hrms.attendance.default_half_day_minutes', 240);
                if ($approvedLeave && $approvedLeave->day_mode !== 'full') {
                    $status = 'half_day';
                    $leaveCode = $approvedLeave->leaveType?->code;
                } elseif ($lastOut && $workedMinutes < $halfMin) {
                    $status = 'half_day';
                } else {
                    $status = 'present';
                }
            } elseif ($isHoliday) {
                $status = 'holiday';
            } elseif ($isWeekOff) {
                $status = 'week_off';
            } elseif ($approvedLeave) {
                $status = $approvedLeave->day_mode === 'full' ? 'on_leave' : 'half_day';
                $leaveCode = $approvedLeave->leaveType?->code;
            } else {
                $status = 'absent';
            }

            $attributes = [
                'shift_id' => $shift?->id,
                'first_in_at' => $firstIn,
                'last_out_at' => $lastOut,
                'worked_minutes' => $workedMinutes,
                'break_minutes' => $breakMinutes,
                'late_minutes' => $lateMinutes,
                'early_out_minutes' => $earlyOutMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'status' => $status,
                'leave_code' => $leaveCode,
                'is_late' => $lateMinutes > 0,
                'is_early_out' => $earlyOutMinutes > 0,
                'is_geofence_flagged' => $isGeofenceFlagged,
            ];

            if ($existing) {
                $existing->update($attributes);
                $day = $existing;
            } else {
                $day = AttendanceDay::create(array_merge([
                    'employee_id' => $employee->id,
                    'attendance_date' => $dateStr,
                ], $attributes));
            }

            if ($overtimeMinutes > 0) {
                OvertimeApproval::updateOrCreate(
                    ['attendance_day_id' => $day->id],
                    [
                        'employee_id' => $employee->id,
                        'attendance_date' => $dateStr,
                        'computed_ot_minutes' => $overtimeMinutes,
                    ]
                );
            }

            return $day;
        });
    }
}
