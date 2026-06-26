<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs_manager')->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_files');
    }
};
