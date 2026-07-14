<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->boolean('need_expense')->default(false);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('need_expense')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropColumn('need_expense');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('need_expense');
        });
    }
};
