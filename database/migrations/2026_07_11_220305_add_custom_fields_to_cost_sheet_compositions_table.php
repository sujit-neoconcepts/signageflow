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
        Schema::table('cost_sheet_compositions', function (Blueprint $table) {
            $table->string('custom_name')->nullable()->after('child_cost_sheet_id');
            $table->decimal('custom_unit_price', 15, 4)->nullable()->after('custom_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_sheet_compositions', function (Blueprint $table) {
            $table->dropColumn(['custom_name', 'custom_unit_price']);
        });
    }
};
