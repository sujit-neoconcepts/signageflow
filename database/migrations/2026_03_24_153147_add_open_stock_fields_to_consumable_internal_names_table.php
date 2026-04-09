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
        Schema::table('consumable_internal_names', function (Blueprint $table) {
            $table->tinyInteger('openStockUnit')->default(0)->after('unitAltName');
            $table->decimal('openStockMarginPercent', 8, 2)->default(0)->after('openStockUnit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumable_internal_names', function (Blueprint $table) {
            $table->dropColumn(['openStockUnit', 'openStockMarginPercent']);
        });
    }
};
