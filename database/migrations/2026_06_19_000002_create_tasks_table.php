<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->dateTime('due_date');
            $table->enum('status', ['pending', 'accepted', 'in_progress', 'completed', 'verified', 'closed'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            // Recurrence fields
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence_type', ['daily', 'weekly', 'monthly', 'yearly'])->nullable();
            $table->json('recurrence_config')->nullable(); // Stores day selections (e.g. [1, 3, 5]) or settings
            $table->date('recurrence_end_date')->nullable();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->onDelete('set null');
            $table->dateTime('last_recurrence_generated_at')->nullable();

            // Notifications
            $table->json('notify_channels')->nullable(); // ['email', 'whatsapp', 'mobile']
            $table->integer('reminder_before_due')->default(60); // in minutes
            $table->boolean('reminder_sent')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
