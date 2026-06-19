<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\TaskNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'task:generate-recurring';

    protected $description = 'Generate new task instances for active recurring tasks';

    public function handle()
    {
        $this->info('Generating recurring task instances...');

        $today = Carbon::today();

        // Fetch active recurring templates
        $templates = Task::where('is_recurring', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('recurrence_end_date')
                    ->orWhere('recurrence_end_date', '>=', $today->format('Y-m-d'));
            })
            ->with(['assignees', 'viewers', 'files'])
            ->get();

        $count = 0;

        foreach ($templates as $template) {
            // Check if already generated today
            if ($template->last_recurrence_generated_at &&
                Carbon::parse($template->last_recurrence_generated_at)->isToday()) {
                continue;
            }

            $shouldGenerate = false;
            $dueTime = Carbon::parse($template->due_date)->format('H:i:s');

            switch ($template->recurrence_type) {
                case 'daily':
                    $shouldGenerate = true;
                    break;
                case 'weekly':
                    $dayOfWeek = $today->dayOfWeekIso; // 1 (Mon) - 7 (Sun)
                    $days = $template->recurrence_config['days'] ?? [];
                    if (in_array($dayOfWeek, $days)) {
                        $shouldGenerate = true;
                    }
                    break;
                case 'monthly':
                    $templateDay = Carbon::parse($template->due_date)->day;
                    if ($today->day === $templateDay) {
                        $shouldGenerate = true;
                    }
                    break;
                case 'yearly':
                    $templateDate = Carbon::parse($template->due_date);
                    if ($today->month === $templateDate->month && $today->day === $templateDate->day) {
                        $shouldGenerate = true;
                    }
                    break;
            }

            if ($shouldGenerate) {
                DB::beginTransaction();
                try {
                    $newDueDate = Carbon::parse($today->format('Y-m-d').' '.$dueTime);

                    // Create new task instance
                    $newTask = Task::create([
                        'title' => $template->title,
                        'description' => $template->description,
                        'creator_id' => $template->creator_id,
                        'due_date' => $newDueDate,
                        'status' => 'pending',
                        'priority' => $template->priority,
                        'is_recurring' => false,
                        'parent_task_id' => $template->id,
                        'notify_channels' => $template->notify_channels,
                        'reminder_before_due' => $template->reminder_before_due,
                        'reminder_sent' => false,
                    ]);

                    // Sync assignees
                    $assigneeIds = $template->assignees->pluck('id')->all();
                    $newTask->assignees()->attach($assigneeIds, ['status' => 'pending']);

                    // Sync loop users
                    $viewerIds = $template->viewers->pluck('id')->all();
                    $newTask->viewers()->attach($viewerIds);

                    // Copy files references
                    foreach ($template->files as $file) {
                        $newTask->files()->create([
                            'file_path' => $file->file_path,
                            'file_name' => $file->file_name,
                            'file_type' => $file->file_type,
                            'file_size' => $file->file_size,
                        ]);
                    }

                    // Update template last generated timestamp
                    $template->update(['last_recurrence_generated_at' => Carbon::now()]);

                    DB::commit();

                    // Send notifications
                    TaskNotificationService::notifyNewTask($newTask);

                    $this->line("Generated task instance: {$newTask->title} (Due: {$newDueDate->format('d-m-Y H:i')})");
                    $count++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Failed to generate task for template #{$template->id}: ".$e->getMessage());
                }
            }
        }

        $this->info("Completed generating recurring tasks. Created {$count} new instances.");

        return Command::SUCCESS;
    }
}
