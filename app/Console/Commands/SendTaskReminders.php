<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\TaskNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTaskReminders extends Command
{
    protected $signature = 'task:send-reminders';

    protected $description = 'Send reminder notifications to task assignees prior to the task due date';

    public function handle()
    {
        $this->info('Checking tasks for due date reminders...');

        // Fetch tasks that have not sent reminders and are active
        $tasks = Task::where('reminder_sent', false)
            ->whereNotIn('status', ['completed', 'verified', 'closed'])
            ->with('assignees')
            ->get();

        $count = 0;
        $now = Carbon::now();

        foreach ($tasks as $task) {
            $dueDate = Carbon::parse($task->due_date);
            $reminderMinutes = (int) $task->reminder_before_due;

            // Calculate when reminder should be sent
            $reminderTime = $dueDate->copy()->subMinutes($reminderMinutes);

            if ($now->greaterThanOrEqualTo($reminderTime)) {
                // Send reminder to all assignees who have not completed yet
                foreach ($task->assignees as $assignee) {
                    if (! in_array($assignee->pivot->status, ['completed', 'verified', 'closed'])) {
                        TaskNotificationService::notifyReminder($task, $assignee);
                        $this->line("Sent reminder to {$assignee->name} for task: {$task->title}");
                    }
                }
                $task->update(['reminder_sent' => true]);
                $count++;
            }
        }

        $this->info("Completed sending reminders for {$count} tasks.");

        return Command::SUCCESS;
    }
}
