<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_stage_executives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_stage_id')->constrained('workflow_stages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['workflow_stage_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_stage_executives');
    }
};
