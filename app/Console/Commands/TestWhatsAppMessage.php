<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestWhatsAppMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:send 
                            {phone? : The recipient phone number (with country code, e.g. 919876543210)} 
                            {message? : The test message to send}
                            {--template= : Send a template instead of text (e.g. task_notification)}
                            {--name= : Name of the recipient (template parameter {{1}})}
                            {--task= : Name of the task (template parameter {{2}})}
                            {--lang=en : Language code for the template}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test WhatsApp message or approved template using Meta WhatsApp Business Cloud API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phone = $this->argument('phone');
        if (empty($phone)) {
            $phone = $this->ask('Enter recipient phone number (with country code, e.g., 919876543210)');
        }

        if (empty($phone)) {
            $this->error('Recipient phone number is required.');

            return Command::FAILURE;
        }

        // Sanitize phone number (remove non-digits)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        // Default to prepend 91 for 10-digit Indian numbers
        if (strlen($cleanPhone) === 10) {
            $cleanPhone = '91'.$cleanPhone;
        }

        $phoneId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');

        $this->info('WhatsApp API Configurations:');
        $this->line('Phone Number ID: '.($phoneId ?: 'NOT SET'));
        $this->line('Access Token: '.($accessToken ? substr($accessToken, 0, 10).'...'.substr($accessToken, -10) : 'NOT SET'));

        if (empty($phoneId) || empty($accessToken)) {
            $this->error('WhatsApp Business API credentials are not fully set in config or .env.');

            return Command::FAILURE;
        }

        $template = $this->option('template');
        $isTemplate = ! empty($template);

        // If no template option and no message argument are specified, ask the user interactively
        if (! $isTemplate && empty($this->argument('message'))) {
            $choice = $this->choice('Which type of message would you like to send?', [
                'text' => 'Custom Text Message (subject to 24h window limit)',
                'template' => 'Approved "task_notification" Template Message',
            ], 'template');

            if ($choice === 'template') {
                $isTemplate = true;
                $template = 'task_notification';
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
        ];

        if ($isTemplate) {
            $templateName = $template ?: 'task_notification';
            $lang = $this->option('lang') ?: 'en';

            $this->info("Preparing template message: {$templateName} (Language: {$lang})");

            $parameters = [];
            if ($templateName === 'task_notification') {
                $name = $this->option('name');
                if (empty($name)) {
                    $name = $this->ask('Enter recipient name (parameter {{1}})');
                }
                if (empty($name)) {
                    $this->error('Recipient name is required for task_notification template.');

                    return Command::FAILURE;
                }

                $taskName = $this->option('task');
                if (empty($taskName)) {
                    $taskName = $this->ask('Enter task name (parameter {{2}})');
                }
                if (empty($taskName)) {
                    $this->error('Task name is required for task_notification template.');

                    return Command::FAILURE;
                }

                $parameters = [
                    [
                        'type' => 'text',
                        'text' => $name,
                    ],
                    [
                        'type' => 'text',
                        'text' => $taskName,
                    ],
                ];
            } else {
                $this->warn("Sending custom template '{$templateName}'. No parameters will be passed unless configured.");
            }

            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $templateName,
                'language' => [
                    'code' => $lang,
                ],
            ];

            if (! empty($parameters)) {
                $payload['template']['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => $parameters,
                    ],
                ];
            }
        } else {
            $message = $this->argument('message');
            if (empty($message)) {
                $message = 'This is a test message from SignageFlow via WhatsApp Business API.';
            }

            $this->info('Preparing custom text message...');
            $this->line("Message: \"{$message}\"");

            $payload['type'] = 'text';
            $payload['text'] = [
                'preview_url' => false,
                'body' => $message,
            ];
        }

        $apiUrl = "https://graph.facebook.com/v19.0/{$phoneId}/messages";

        try {
            $this->info('Sending request to Meta WhatsApp Business API...');
            $response = Http::withToken($accessToken)->post($apiUrl, $payload);

            if ($response->successful()) {
                $this->info('WhatsApp message sent successfully via WhatsApp Business Cloud API!');
                $this->line('Response: '.$response->body());

                return Command::SUCCESS;
            } else {
                $this->error("Failed to send WhatsApp message. Status: {$response->status()}");
                $this->error("Response: {$response->body()}");

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Error occurred while sending request: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
