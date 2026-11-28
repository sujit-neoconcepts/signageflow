<?php

namespace SignageFlow\Hrms\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Services\AttendanceCalculationService;

class DailyAttendanceRollupCommand extends Command
{
    protected $signature = 'hrms:daily-attendance-rollup {--date= : Date in Y-m-d format (defaults to today)}';

    protected $description = 'Calculate daily attendance status for all active HRMS employees';

    public function handle(AttendanceCalculationService $service): int
    {
        $date = Carbon::parse($this->option('date') ?? now()->toDateString());
        $employees = Employee::active()->get();

        foreach ($employees as $employee) {
            $service->recalculateDay($employee, $date);
        }

        $this->info("Processed attendance rollup for {$employees->count()} employees on {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
