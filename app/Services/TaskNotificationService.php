<?php

namespace App\Services;

use App\Mail\MeideMail;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TaskNotificationService
{
    /**
     * Notify assignees and viewers about a new task or update.
     */
    public static function notifyNewTask(Task $task)
    {
        $task->load(['creator', 'assignees', 'viewers', 'job']);
        $channels = $task->notify_channels ?? ['email'];

        $title = "New Task Assigned: {$task->title}";
        $taskDetail = self::getDetailedTaskString($task);

        // Notify Assignees
        foreach ($task->assignees as $assignee) {
            $body = "Hello {$assignee->name},<br><br>".
                    "You have been assigned a new task by {$task->creator->name}.<br><br>".
                    "<strong>Task Title:</strong> {$task->title}<br>".
                    "<strong>Description:</strong> {$task->description}<br>".
                    '<strong>Priority:</strong> '.ucfirst($task->priority).'<br>'.
                    '<strong>Due Date:</strong> '.$task->due_date->format('d-m-Y H:i').'<br><br>'.
                    "Please log in to the portal and check your 'My Tasks' section to accept and work on the task.<br>";

            self::send($assignee, $title, $body, $channels, [
                'name' => config('services.whatsapp.templates.notification', 'task_notification'),
                'parameters' => [
                    $assignee->name,
                    $taskDetail,
                ],
            ]);
        }

        // Notify Loop Users (Viewers)
        foreach ($task->viewers as $viewer) {
            $body = "Hello {$viewer->name},<br><br>".
                    "You have been put in the loop for a task created by {$task->creator->name}.<br><br>".
                    "<strong>Task Title:</strong> {$task->title}<br>".
                    "<strong>Description:</strong> {$task->description}<br>".
                    '<strong>Priority:</strong> '.ucfirst($task->priority).'<br>'.
                    '<strong>Due Date:</strong> '.$task->due_date->format('d-m-Y H:i').'<br><br>'.
                    'This is a view-only notification for your reference.<br>';

            self::send($viewer, "Loop Notification: {$task->title}", $body, $channels, [
                'name' => config('services.whatsapp.templates.loop', 'task_loop_notification'),
                'parameters' => [
                    $viewer->name,
                    $taskDetail,
                ],
            ]);
        }
    }

    /**
     * Notify assignees about an upcoming task due date (Reminder).
     */
    public static function notifyReminder(Task $task, User $assignee)
    {
        $task->load(['job']);
        $channels = $task->notify_channels ?? ['email'];
        $title = "Reminder: Task Due Soon - {$task->title}";
        $body = "Hello {$assignee->name},<br><br>".
                'This is a reminder that the following task is due soon.<br><br>'.
                "<strong>Task Title:</strong> {$task->title}<br>".
                '<strong>Due Date:</strong> '.$task->due_date->format('d-m-Y H:i').'<br><br>'.
                'Please submit your progress or mark the task as completed.';

        $taskDetail = self::getDetailedTaskString($task);

        self::send($assignee, $title, $body, $channels, [
            'name' => config('services.whatsapp.templates.reminder', 'task_reminder'),
            'parameters' => [
                $assignee->name,
                $taskDetail,
            ],
        ]);
    }

    /**
     * Notify task creator about assignee status updates.
     */
    public static function notifyStatusUpdate(Task $task, User $assignee, string $newStatus, ?string $comment = null)
    {
        $task->load(['creator', 'job']);
        $channels = $task->notify_channels ?? ['email'];

        $title = "Task Update: {$assignee->name} marked task as ".ucfirst($newStatus);
        $body = "Hello {$task->creator->name},<br><br>".
                "{$assignee->name} has updated the status of their assigned task.<br><br>".
                "<strong>Task Title:</strong> {$task->title}<br>".
                '<strong>New Status:</strong> '.ucfirst($newStatus).'<br>'.
                ($comment ? "<strong>Comment/Feedback:</strong> {$comment}<br>" : '').
                '<strong>Updated At:</strong> '.now()->format('d-m-Y H:i').'<br><br>'.
                'Please review the task in the Task Manager.';

        $taskDetail = self::getDetailedTaskString($task);
        $statusStr = ucfirst($newStatus);
        if (! empty($comment)) {
            $statusStr .= " (Comment: {$comment})";
        }

        self::send($task->creator, $title, $body, $channels, [
            'name' => config('services.whatsapp.templates.status_update', 'task_status_update'),
            'parameters' => [
                $task->creator->name,
                $taskDetail,
                $statusStr,
                $assignee->name,
            ],
        ]);
    }

    /**
     * Notify assignees that a task has started.
     */
    public static function notifyTaskStart(Task $task)
    {
        $task->load(['creator', 'assignees', 'job']);
        $channels = $task->notify_channels ?? ['email'];

        $title = "Task Started: {$task->title}";
        $taskDetail = self::getDetailedTaskString($task);

        foreach ($task->assignees as $assignee) {
            $body = "Hello {$assignee->name},<br><br>".
                    'The task assigned to you has now started and is ready to accept.<br><br>'.
                    "<strong>Task Title:</strong> {$task->title}<br>".
                    "<strong>Description:</strong> {$task->description}<br>".
                    '<strong>Priority:</strong> '.ucfirst($task->priority).'<br>'.
                    '<strong>Start Date/Time:</strong> '.($task->start_date ? $task->start_date->format('d-m-Y H:i') : 'N/A').'<br>'.
                    '<strong>Due Date:</strong> '.$task->due_date->format('d-m-Y H:i').'<br><br>'.
                    'Please log in to the portal, accept, and start working on the task.<br>';

            self::send($assignee, $title, $body, $channels, [
                'name' => config('services.whatsapp.templates.notification', 'task_notification'),
                'parameters' => [
                    $assignee->name,
                    $taskDetail,
                ],
            ]);
        }
    }

    /**
     * Notify task creator about activity updates (comments, file uploads, voice notes, expenses) done by assignees.
     */
    public static function notifyActivityUpdate(Task $task, User $actor, string $activityType, ?string $detail = null)
    {
        $task->load(['creator', 'job']);

        // Don't notify the creator about their own activities
        if ($task->creator_id === $actor->id) {
            return;
        }

        $channels = $task->notify_channels ?? ['email'];

        $activityLabel = '';
        switch ($activityType) {
            case 'comment':
                $activityLabel = 'added a comment';
                break;
            case 'file_upload':
                $activityLabel = 'uploaded a file';
                break;
            case 'voice_note':
                $activityLabel = 'recorded a voice note';
                break;
            case 'expense':
                $activityLabel = 'added an expense';
                break;
            default:
                $activityLabel = 'updated activity';
        }

        $title = "Task Activity: {$actor->name} {$activityLabel}";
        $body = "Hello {$task->creator->name},<br><br>".
                "{$actor->name} has performed an activity on the task.<br><br>".
                "<strong>Task Title:</strong> {$task->title}<br>".
                '<strong>Activity:</strong> '.ucfirst($activityType).'<br>'.
                ($detail ? "<strong>Details:</strong> {$detail}<br>" : '').
                '<strong>Date/Time:</strong> '.now()->format('d-m-Y H:i').'<br><br>'.
                'Please review the task in the Task Manager.';

        $taskDetail = self::getDetailedTaskString($task);
        $activitySummary = "{$actor->name} {$activityLabel}".($detail ? ": {$detail}" : '');

        self::send($task->creator, $title, $body, $channels, [
            'name' => config('services.whatsapp.templates.activity_update', 'task_activity_update'),
            'parameters' => [
                $task->creator->name,
                $taskDetail,
                $activitySummary,
            ],
        ]);
    }

    /**
     * Notify all assignees and viewers when a job completes.
     */
    public static function notifyJobCompleted(\App\Models\Job $job)
    {
        $job->load(['tasks.assignees', 'tasks.viewers']);

        // Find all unique assignees and viewers across all tasks in the job
        $usersToNotify = collect();

        foreach ($job->tasks as $task) {
            foreach ($task->assignees as $assignee) {
                $usersToNotify->put($assignee->id, $assignee);
            }
            foreach ($task->viewers as $viewer) {
                $usersToNotify->put($viewer->id, $viewer);
            }
        }

        if ($usersToNotify->isEmpty()) {
            return;
        }

        $title = "Job Completed: {$job->title}";
        $jobDetail = "Job: {$job->title}".(! empty($job->description) ? " | {$job->description}" : '');

        // Since notify_channels is task-based, we'll default to ['email', 'whatsapp'] or try to get channels from first task
        $firstTask = $job->tasks->first();
        $channels = $firstTask ? ($firstTask->notify_channels ?? ['email']) : ['email'];

        foreach ($usersToNotify as $user) {
            $body = "Hello {$user->name},<br><br>".
                    "Great news! The job <strong>{$job->title}</strong> has been completed successfully.<br><br>".
                    'All associated workflow stages/tasks are now finished.<br><br>'.
                    'Thank you for your effort!';

            self::send($user, $title, $body, $channels, [
                'name' => config('services.whatsapp.templates.job_completed', 'job_completed'),
                'parameters' => [
                    $user->name,
                    $jobDetail,
                ],
            ]);
        }
    }

    /**
     * Build detailed task details string (task name, job name, description, start time).
     */
    private static function getDetailedTaskString(Task $task): string
    {
        $parts = [];
        $parts[] = $task->title;

        if ($task->job) {
            $parts[] = "Job: {$task->job->title}";
        }

        if (! empty($task->description)) {
            $desc = strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $task->description));
            $desc = trim(preg_replace('/\s+/', ' ', $desc));
            if (mb_strlen($desc) > 80) {
                $desc = mb_substr($desc, 0, 77).'...';
            }
            $parts[] = "Desc: {$desc}";
        }

        if ($task->start_date) {
            $parts[] = 'Start: '.$task->start_date->format('d-m-Y H:i');
        }

        return implode(' | ', $parts);
    }

    /**
     * Sanitize a parameter value to comply with Meta's strict template parameter constraints:
     * - No newlines (\n, \r)
     * - No tabs (\t)
     * - No more than 4 consecutive spaces
     */
    private static function sanitizeParamValue(string $value): string
    {
        // Replace newlines and tabs with spaces
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        // Replace 2 or more consecutive spaces with a single space
        $value = preg_replace('/\s{2,}/', ' ', $value);

        return trim($value);
    }

    /**
     * Internal sender router.
     */
    private static function send(User $user, string $subject, string $body, array $channels, ?array $whatsappTemplate = null)
    {
        // 1. Email Notification
        if (in_array('email', $channels) && ! empty($user->email)) {
            try {
                $details = [
                    'title' => $subject,
                    'subject' => $subject,
                    'body' => $body,
                ];
                Mail::to($user->email)->send(new MeideMail($details));
            } catch (\Exception $e) {
                Log::error("Failed to send email to {$user->email}: ".$e->getMessage());
            }
        }

        // 2. WhatsApp Notification (Official Meta WhatsApp Business Cloud API)
        if (in_array('whatsapp', $channels) && ! empty($user->phone)) {
            try {
                $phoneId = config('services.whatsapp.phone_number_id');
                $accessToken = config('services.whatsapp.access_token');

                if ($phoneId && $accessToken) {
                    // Sanitize phone number (remove non-digits)
                    $phone = preg_replace('/[^0-9]/', '', $user->phone);
                    // Default to prepend 91 for 10-digit Indian numbers
                    if (strlen($phone) === 10) {
                        $phone = '91'.$phone;
                    }

                    $apiUrl = "https://graph.facebook.com/v19.0/{$phoneId}/messages";

                    if ($whatsappTemplate) {
                        $payload = [
                            'messaging_product' => 'whatsapp',
                            'recipient_type' => 'individual',
                            'to' => $phone,
                            'type' => 'template',
                            'template' => [
                                'name' => $whatsappTemplate['name'],
                                'language' => [
                                    'code' => $whatsappTemplate['lang'] ?? 'en',
                                ],
                            ],
                        ];

                        if (! empty($whatsappTemplate['parameters'])) {
                            $payload['template']['components'] = [
                                [
                                    'type' => 'body',
                                    'parameters' => array_map(function ($param) {
                                        return [
                                            'type' => 'text',
                                            'text' => self::sanitizeParamValue((string) $param),
                                        ];
                                    }, $whatsappTemplate['parameters']),
                                ],
                            ];
                        }
                    } else {
                        $plainBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
                        $messageText = "*{$subject}*\n\n{$plainBody}";

                        $payload = [
                            'messaging_product' => 'whatsapp',
                            'recipient_type' => 'individual',
                            'to' => $phone,
                            'type' => 'text',
                            'text' => [
                                'preview_url' => false,
                                'body' => $messageText,
                            ],
                        ];
                    }

                    // Send payload using official WhatsApp Business API format
                    $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
                        ->post($apiUrl, $payload);

                    if ($response->successful()) {
                        Log::info("WhatsApp notification sent to {$phone} (User: {$user->name}) successfully via WhatsApp Business Cloud API.");
                    } else {
                        Log::error("Failed to send WhatsApp to {$phone} (User: {$user->name}). Status: {$response->status()}, Response: {$response->body()}");
                    }
                } else {
                    $phone = $user->phone ?? 'N/A';
                    Log::warning("WhatsApp Business API credentials not fully set in config or .env. Logging placeholder: *{$subject}*");
                    Log::info("WhatsApp Notification (Placeholder) sent to {$phone} (User: {$user->name}): {$subject} - ".strip_tags($body));
                }
            } catch (\Exception $e) {
                Log::error("Failed to send WhatsApp to {$user->phone} (User: {$user->name}): ".$e->getMessage());
            }
        }

        // 3. Mobile App Notification (Placeholder)
        if (in_array('mobile', $channels)) {
            Log::info("Mobile App Notification (Placeholder) sent to User ID {$user->id} ({$user->name}): {$subject} - ".strip_tags($body));
        }
    }
}
