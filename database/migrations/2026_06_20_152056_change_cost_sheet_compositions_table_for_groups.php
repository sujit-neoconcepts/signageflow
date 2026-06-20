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
            $table->dropForeign(['consumable_internal_name_id']);
            $table->dropColumn('consumable_internal_name_id');

            $table->unsignedBigInteger('consumable_internal_name_group_id')->nullable()->after('cost_sheet_id');
            $table->foreign('consumable_internal_name_group_id', 'csc_group_id_foreign')
                ->references('id')
                ->on('consumable_internal_name_groups')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_sheet_compositions', function (Blueprint $table) {
            $table->dropForeign('csc_group_id_foreign');
            $table->dropColumn('consumable_internal_name_group_id');

            $table->unsignedBigInteger('consumable_internal_name_id')->nullable()->after('cost_sheet_id');
            $table->foreign('consumable_internal_name_id')
                ->references('id')
                ->on('consumable_internal_names')
                ->onDelete('cascade');
        });
    }
};
