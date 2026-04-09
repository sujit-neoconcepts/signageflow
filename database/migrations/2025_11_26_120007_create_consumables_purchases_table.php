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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->date('pur_date')->index();
            $table->string('pur_inv');
            $table->string('pur_supplier');
            $table->integer('pur_pr_id');
            $table->string('pur_pr_detail');
            $table->string('pur_pr_hsn');
            $table->string('pur_pr_detail_int')->index();
            $table->decimal('pur_qty', 11, 2);
            $table->decimal('pur_qty_int', 11, 2);
            $table->string('pur_unit');
            $table->string('pur_unint_int');
            $table->decimal('pur_gst', 11, 2)->nullable();
            $table->decimal('pur_amnt', 11, 2);
            $table->decimal('pur_gst_amnt', 11, 2);
            $table->decimal('pur_amnt_total', 11, 2);
            $table->decimal('pur_rate', 11, 2);
            $table->decimal('pur_rate_int', 11, 2);
            $table->decimal('pur_qty_alt', 11, 2)->nullable();
            $table->string('pur_unit_alt')->nullable();
            $table->decimal('pur_qty_int_alt', 11, 2)->nullable();
            $table->string('pur_unint_int_alt')->nullable();
            $table->decimal('pur_unit_conv_rate', 19, 8)->nullable();
            $table->string('pur_loc')->nullable()->index();
            $table->string('pur_incharge')->nullable()->index();
            $table->integer('entry_type')->default(0);
            $table->string('remark')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
