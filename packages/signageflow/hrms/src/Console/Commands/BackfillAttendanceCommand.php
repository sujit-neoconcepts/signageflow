<?php

namespace SignageFlow\Hrms\Console\Commands;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Services\AttendanceCalculationService;

class BackfillAttendanceCommand extends Command
{
    protected $signature = 'hrms:backfill-attendance {from : Start date Y-m-d} {to : End date Y-m-d} {--employee= : Optional employee ID}';

    protected $description = 'Create missing attendance day records across a date range';

    public function handle(AttendanceCalculationService $service): int
    {
        $from = Carbon::parse($this->argument('from'));
        $to = Carbon::parse($this->argument('to'));
        $employeeId = $this->option('employee');

        $employees = Employee::active()
            ->when($employeeId, fn ($q) => $q->where('id', $employeeId))
            ->get();

        $total = 0;
        foreach (CarbonPeriod::create($from, $to) as $date) {
            foreach ($employees as $employee) {
                $service->recalculateDay($employee, $date);
                $total++;
            }
        }

        $this->info("Backfilled {$total} attendance day records.");

        return self::SUCCESS;
    }
}
