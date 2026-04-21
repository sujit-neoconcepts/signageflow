<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cost_sheets', function (Blueprint $table) {
            $table->unsignedInteger('no_of_unit')->default(1)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_sheets', function (Blueprint $table) {
            $table->dropColumn('no_of_unit');
        });
    }
};
