<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_sheet_compositions', function (Blueprint $table) {
            $table->string('section')->default('raw_material')->after('cost_sheet_id');
            $table->unsignedBigInteger('child_cost_sheet_id')->nullable()->after('section');
            $table->foreign('child_cost_sheet_id')->references('id')->on('cost_sheets')->onDelete('set null');
            $table->unsignedBigInteger('consumable_internal_name_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cost_sheet_compositions', function (Blueprint $table) {
            $table->dropForeign(['child_cost_sheet_id']);
            $table->dropColumn(['section', 'child_cost_sheet_id']);
        });
    }
};
