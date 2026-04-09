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
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('pur_supplier')->nullable()->change();
            $table->string('pur_pr_detail')->nullable()->change();
            $table->string('pur_pr_hsn')->nullable()->change();
            $table->decimal('pur_qty', 11, 2)->nullable()->change();
            $table->string('pur_unit')->nullable()->change();
            $table->decimal('pur_rate', 11, 2)->nullable()->change();
            $table->decimal('pur_gst_amnt', 11, 2)->nullable()->change();
            $table->decimal('pur_amnt_total', 11, 2)->nullable()->change();
            $table->decimal('pur_amnt', 11, 2)->nullable()->change(); // Also pur_amnt seems to be calculated/required usually but might be missing in opening
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('pur_supplier')->nullable(false)->change();
            $table->string('pur_pr_detail')->nullable(false)->change();
            $table->string('pur_pr_hsn')->nullable(false)->change();
            $table->decimal('pur_qty', 11, 2)->nullable(false)->change();
            $table->string('pur_unit')->nullable(false)->change();
            $table->decimal('pur_rate', 11, 2)->nullable(false)->change();
            $table->decimal('pur_gst_amnt', 11, 2)->nullable(false)->change();
            $table->decimal('pur_amnt_total', 11, 2)->nullable(false)->change();
            $table->decimal('pur_amnt', 11, 2)->nullable(false)->change();
        });
    }
};
