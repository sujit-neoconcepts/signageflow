<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('job_id')->nullable()->after('parent_task_id')->constrained('jobs_manager')->onDelete('set null');
            $table->integer('job_stage_sort_order')->nullable()->after('job_id');
            $table->dateTime('start_date')->nullable()->after('due_date');
            $table->dateTime('end_date')->nullable()->after('start_date');
            $table->decimal('estimated_hours', 8, 2)->nullable()->after('end_date');
            $table->boolean('start_on_previous_complete')->default(false)->after('estimated_hours');

            $table->index(['job_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
            $table->dropIndex(['job_id']);
            $table->dropColumn([
                'job_id',
                'job_stage_sort_order',
                'start_date',
                'end_date',
                'estimated_hours',
                'start_on_previous_complete',
            ]);
        });
    }
};
