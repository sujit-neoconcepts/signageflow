<?php

namespace SignageFlow\Hrms\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\NotificationLog;
use SignageFlow\Hrms\Models\NotificationTemplate;

class HrmsNotificationService
{
    public function dispatch(string $eventKey, Employee $recipient, array $variables = []): void
    {
        $template = NotificationTemplate::where('event_key', $eventKey)->where('is_active', true)->first();
        $subject = $this->interpolate($template?->subject_template ?? ucwords(str_replace('_', ' ', $eventKey)), $variables);
        $body = $this->interpolate($template?->body_template ?? '', $variables);

        $user = $recipient->user;
        $email = $recipient->official_email ?: $user?->email;

        if (($template?->in_app_enabled ?? true) && $user && class_exists(\App\Models\UserNotification::class)) {
            try {
                \App\Models\UserNotification::create([
                    'user_id' => $user->id,
                    'title' => $subject,
                    'message' => strip_tags($body),
                    'type' => 'hrms_'.$eventKey,
                    'is_read' => false,
                ]);
                $this->log($eventKey, $recipient, 'in_app', 'sent', $subject);
            } catch (\Throwable $e) {
                $this->log($eventKey, $recipient, 'in_app', 'failed', $subject, $e->getMessage());
            }
        }

        if (($template?->push_enabled ?? true) && $user && class_exists(\App\Services\FirebaseService::class)) {
            try {
                $tokens = $user->deviceTokens()->pluck('token');
                foreach ($tokens as $token) {
                    \App\Services\FirebaseService::sendPushNotification($token, $subject, strip_tags($body), ['event' => $eventKey]);
                }
                if ($tokens->isNotEmpty()) {
                    $this->log($eventKey, $recipient, 'push', 'sent', $subject);
                }
            } catch (\Throwable $e) {
                $this->log($eventKey, $recipient, 'push', 'failed', $subject, $e->getMessage());
            }
        }

        if (($template?->email_enabled ?? true) && $email && class_exists(\App\Mail\MeideMail::class)) {
            try {
                Mail::to($email)->send(new \App\Mail\MeideMail([
                    'title' => $subject,
                    'subject' => $subject,
                    'body' => nl2br(e($body)),
                ]));
                $this->log($eventKey, $recipient, 'email', 'sent', $subject);
            } catch (\Throwable $e) {
                Log::warning('HRMS email dispatch failed: '.$e->getMessage());
                $this->log($eventKey, $recipient, 'email', 'failed', $subject, $e->getMessage());
            }
        }
    }

    protected function interpolate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{'.$key.'}}', (string) $value, $template);
        }

        return $template;
    }

    protected function log(string $eventKey, Employee $recipient, string $channel, string $status, string $subject, ?string $error = null): void
    {
        NotificationLog::create([
            'event_key' => $eventKey,
            'recipient_user_id' => $recipient->user_id,
            'recipient_employee_id' => $recipient->id,
            'channel' => $channel,
            'status' => $status,
            'subject' => $subject,
            'error_message' => $error,
            'sent_at' => now(),
        ]);
    }
}
