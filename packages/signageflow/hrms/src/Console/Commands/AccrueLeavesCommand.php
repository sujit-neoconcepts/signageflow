<?php

namespace SignageFlow\Hrms\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use SignageFlow\Hrms\Services\LeaveBalanceService;

class AccrueLeavesCommand extends Command
{
    protected $signature = 'hrms:accrue-leaves {--date= : Reference date in Y-m-d format}';

    protected $description = 'Run scheduled FY leave accruals for active HRMS employees';

    public function handle(LeaveBalanceService $service): int
    {
        $date = Carbon::parse($this->option('date') ?? now()->toDateString());
        $count = $service->runAccrualForMonth($date);

        $this->info("Processed {$count} leave accrual entries for {$date->format('Y-m')}.");

        return self::SUCCESS;
    }
}
