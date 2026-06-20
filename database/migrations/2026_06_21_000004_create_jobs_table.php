<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_manager', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->foreignId('workflow_id')->nullable()->constrained('workflows')->onDelete('set null');
            $table->dateTime('due_date');
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'closed'])->default('not_started');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['status']);
            $table->index(['client_id']);
            $table->index(['workflow_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_manager');
    }
};
