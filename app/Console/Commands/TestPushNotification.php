<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Console\Command;

class TestPushNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test push notification to a user by email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->ask('Enter user email address');

        if (empty($email)) {
            $this->error('Email address cannot be empty.');

            return 1;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email '{$email}' not found.");

            return 1;
        }

        $tokens = $user->deviceTokens;

        if ($tokens->isEmpty()) {
            $this->error("User '{$user->name}' does not have any registered mobile device tokens.");

            return 1;
        }

        $this->info("Found {$tokens->count()} device token(s) for user '{$user->name}'.");

        $title = $this->ask('Enter notification title', 'Test Notification');
        $body = $this->ask('Enter notification body', 'This is a test notification from SignageFlow.');

        // Save to database first so it shows up in the mobile app notification listing
        try {
            \App\Models\UserNotification::create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
            ]);
        } catch (\Exception $e) {
            $this->error('Failed to save notification to database: '.$e->getMessage());
        }

        $successCount = 0;
        foreach ($tokens as $index => $deviceToken) {
            $platform = $deviceToken->platform ?? 'unknown platform';
            $this->comment('Sending notification to device '.($index + 1)." ({$platform})...");
            $success = FirebaseService::sendPushNotification($deviceToken->token, $title, $body, [
                'type' => 'test',
                'timestamp' => now()->toIso8601String(),
            ]);

            if ($success) {
                $successCount++;
                $this->info('Successfully sent notification to device '.($index + 1));
            } else {
                $this->error('Failed to send notification to device '.($index + 1).' (Check logs for details).');
            }
        }

        $this->info("Completed. Successfully sent {$successCount} out of {$tokens->count()} push notification(s).");

        return 0;
    }
}
