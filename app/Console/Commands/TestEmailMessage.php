<?php

namespace App\Console\Commands;

use App\Mail\MeideMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestEmailMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test 
                            {email? : The recipient email address}
                            {--mailer= : Specific mailer to test (defaults to default mailer)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email to verify mail driver connectivity and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mailer = $this->option('mailer') ?: config('mail.default');
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $this->info('--- SignageFlow Mail Diagnostic ---');
        $this->line("Active Mailer: <comment>{$mailer}</comment>");
        $this->line("From Address:  <comment>{$fromAddress}</comment> ({$fromName})");

        $mailerConfig = config("mail.mailers.{$mailer}", []);
        $transport = $mailerConfig['transport'] ?? 'unknown';
        $this->line("Transport:     <comment>{$transport}</comment>");

        if ($transport === 'smtp') {
            $host = $mailerConfig['host'] ?? 'not set';
            $port = $mailerConfig['port'] ?? 'not set';
            $encryption = $mailerConfig['encryption'] ?? 'none';
            $hasUser = ! empty($mailerConfig['username']);
            $hasPass = ! empty($mailerConfig['password']);

            $this->line("SMTP Host:     <comment>{$host}:{$port}</comment> (Encryption: {$encryption})");
            $this->line('Credentials:   '.($hasUser && $hasPass ? '<info>Username & Password set</info>' : '<error>Missing Credentials</error>'));
        }

        $this->newLine();

        $email = $this->argument('email');
        if (empty($email)) {
            $email = $this->ask('Enter recipient email address', $fromAddress);
        }

        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid recipient email address: '{$email}'");

            return Command::FAILURE;
        }

        $this->comment("Sending test email to <info>{$email}</info> using <comment>{$mailer}</comment> mailer...");

        $subject = 'SignageFlow Email Test - '.now()->format('d M Y H:i:s');
        $body = 'Hello,<br><br>This is a test email sent from <strong>SignageFlow</strong> to verify that your mail configuration is working correctly.<br><br>'
            ."<strong>Mailer:</strong> {$mailer}<br>"
            ."<strong>Transport:</strong> {$transport}<br>"
            ."<strong>From:</strong> {$fromAddress}<br>"
            .'<strong>Timestamp:</strong> '.now()->toIso8601String().'<br><br>'
            .'If you are seeing this email, your mail transport is operating successfully!';

        try {
            Mail::mailer($mailer)->to($email)->send(new MeideMail([
                'title' => 'SignageFlow Mail Test',
                'subject' => $subject,
                'body' => $body,
            ]));

            $this->info("✓ Success! Test email dispatched to '{$email}'.");
            $this->line("Please check the inbox (and spam/junk folder) of {$email}.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("✗ Failed to send email: {$e->getMessage()}");
            Log::error("Test email to {$email} failed: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return Command::FAILURE;
        }
    }
}
