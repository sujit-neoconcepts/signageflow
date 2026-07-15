<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs_manager', function (Blueprint $table) {
            $table->boolean('job_completed_notified')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('jobs_manager', function (Blueprint $table) {
            $table->dropColumn('job_completed_notified');
        });
    }
};
