<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\TaskNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessWorkflowStages extends Command
{
    protected $signature = 'task:process-workflow';

    protected $description = 'Process workflow stages (setting start_date on previous completion) and sending start notifications';

    public function handle()
    {
        $this->info('Processing workflow tasks...');

        // 1. Fill start_date for tasks where start_on_previous_complete = 1, start_date is NULL, and job_id is NOT NULL
        $waitingTasks = Task::whereNotNull('job_id')
            ->where('start_on_previous_complete', true)
            ->whereNull('start_date')
            ->get();

        $filledCount = 0;
        foreach ($waitingTasks as $task) {
            // Find previous task (job_stage_sort_order = current - 1)
            $prevSortOrder = $task->job_stage_sort_order - 1;
            if ($prevSortOrder >= 0) {
                $prevTask = Task::where('job_id', $task->job_id)
                    ->where('job_stage_sort_order', $prevSortOrder)
                    ->first();

                if ($prevTask && in_array($prevTask->status, ['completed', 'verified', 'closed'])) {
                    $task->update(['start_date' => Carbon::now()]);
                    $this->line("Set start_date for task ID {$task->id} ('{$task->title}') as previous stage completed.");
                    $filledCount++;
                }
            }
        }

        // 2. Notify users for tasks where start_date is in the past, and start_notified is false
        $startingTasks = Task::whereNotNull('start_date')
            ->where('start_date', '<=', Carbon::now())
            ->where('start_notified', false)
            ->whereNotIn('status', ['completed', 'verified', 'closed'])
            ->get();

        $notifyCount = 0;
        foreach ($startingTasks as $task) {
            TaskNotificationService::notifyTaskStart($task);
            $task->update(['start_notified' => true]);
            $this->line("Sent task start notification for task ID {$task->id} ('{$task->title}').");
            $notifyCount++;
        }

        $this->info("Completed processing: filled start_date for {$filledCount} tasks, notified {$notifyCount} tasks.");

        return Command::SUCCESS;
    }
}
