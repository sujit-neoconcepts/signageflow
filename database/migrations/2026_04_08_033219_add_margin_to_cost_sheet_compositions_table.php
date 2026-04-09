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
            $table->decimal('margin', 5, 2)->default(0.00)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_sheet_compositions', function (Blueprint $table) {
            $table->dropColumn('margin');
        });
    }
};
