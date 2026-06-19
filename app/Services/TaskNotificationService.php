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
        $task->load(['creator', 'assignees', 'viewers']);
        $channels = $task->notify_channels ?? ['email'];

        $title = "New Task Assigned: {$task->title}";

        // Notify Assignees
        foreach ($task->assignees as $assignee) {
            $body = "Hello {$assignee->name},<br><br>".
                    "You have been assigned a new task by {$task->creator->name}.<br><br>".
                    "<strong>Task Title:</strong> {$task->title}<br>".
                    "<strong>Description:</strong> {$task->description}<br>".
                    '<strong>Priority:</strong> '.ucfirst($task->priority).'<br>'.
                    '<strong>Due Date:</strong> '.$task->due_date->format('d-m-Y H:i').'<br><br>'.
                    "Please log in to the portal and check your 'My Tasks' section to accept and work on the task.<br>";

            self::send($assignee, $title, $body, $channels);
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

            self::send($viewer, "Loop Notification: {$task->title}", $body, $channels);
        }
    }

    /**
     * Notify assignees about an upcoming task due date (Reminder).
     */
    public static function notifyReminder(Task $task, User $assignee)
    {
        $channels = $task->notify_channels ?? ['email'];
        $title = "Reminder: Task Due Soon - {$task->title}";
        $body = "Hello {$assignee->name},<br><br>".
                'This is a reminder that the following task is due soon.<br><br>'.
                "<strong>Task Title:</strong> {$task->title}<br>".
                '<strong>Due Date:</strong> '.$task->due_date->format('d-m-Y H:i').'<br><br>'.
                'Please submit your progress or mark the task as completed.';

        self::send($assignee, $title, $body, $channels);
    }

    /**
     * Notify task creator about assignee status updates.
     */
    public static function notifyStatusUpdate(Task $task, User $assignee, string $newStatus, ?string $comment = null)
    {
        $task->load('creator');
        $channels = $task->notify_channels ?? ['email'];

        $title = "Task Update: {$assignee->name} marked task as ".ucfirst($newStatus);
        $body = "Hello {$task->creator->name},<br><br>".
                "{$assignee->name} has updated the status of their assigned task.<br><br>".
                "<strong>Task Title:</strong> {$task->title}<br>".
                '<strong>New Status:</strong> '.ucfirst($newStatus).'<br>'.
                ($comment ? "<strong>Comment/Feedback:</strong> {$comment}<br>" : '').
                '<strong>Updated At:</strong> '.now()->format('d-m-Y H:i').'<br><br>'.
                'Please review the task in the Task Manager.';

        self::send($task->creator, $title, $body, $channels);
    }

    /**
     * Internal sender router.
     */
    private static function send(User $user, string $subject, string $body, array $channels)
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

        // 2. WhatsApp Notification (Placeholder)
        if (in_array('whatsapp', $channels)) {
            $phone = $user->phone ?? 'N/A';
            Log::info("WhatsApp Notification (Placeholder) sent to {$phone} (User: {$user->name}): {$subject} - ".strip_tags($body));
        }

        // 3. Mobile App Notification (Placeholder)
        if (in_array('mobile', $channels)) {
            Log::info("Mobile App Notification (Placeholder) sent to User ID {$user->id} ({$user->name}): {$subject} - ".strip_tags($body));
        }
    }
}
