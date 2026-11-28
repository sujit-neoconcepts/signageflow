<?php

namespace SignageFlow\Hrms\Services;

use Carbon\CarbonPeriod;
use SignageFlow\Hrms\Models\FwaRequest;
use SignageFlow\Hrms\Models\LeaveRequest;

class LeaveAttendanceSyncService
{
    public function __construct(
        protected AttendanceCalculationService $attendanceCalculation
    ) {}

    public function syncApprovedLeave(LeaveRequest $request): void
    {
        $employee = $request->employee;
        if (! $employee) {
            return;
        }

        foreach (CarbonPeriod::create($request->start_date, $request->end_date) as $date) {
            $this->attendanceCalculation->recalculateDay($employee, $date);
        }
    }

    public function revertLeave(LeaveRequest $request): void
    {
        $this->syncApprovedLeave($request);
    }

    public function syncApprovedFwa(FwaRequest $request): void
    {
        $employee = $request->employee;
        if (! $employee) {
            return;
        }

        foreach (CarbonPeriod::create($request->start_date, $request->end_date) as $date) {
            $this->attendanceCalculation->recalculateDay($employee, $date);
        }
    }
}
