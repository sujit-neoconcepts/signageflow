<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('job_id')->nullable()->after('id');
            $table->unsignedBigInteger('task_id')->nullable()->after('job_id');

            $table->index('job_id');
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['job_id', 'task_id']);
        });
    }
};
