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
            $table->unsignedBigInteger('consumable_internal_name_group_id')->nullable()->after('id');
            $table->foreign('consumable_internal_name_group_id', 'cin_group_id_foreign')
                ->references('id')
                ->on('consumable_internal_name_groups')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumable_internal_names', function (Blueprint $table) {
            $table->dropForeign('cin_group_id_foreign');
            $table->dropColumn('consumable_internal_name_group_id');
        });
    }
};
