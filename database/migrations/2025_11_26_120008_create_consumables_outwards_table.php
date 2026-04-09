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
        Schema::create('outwards', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->date('out_date')->index();
            $table->string('out_remark')->nullable();
            $table->string('out_incharge')->index();
            $table->string('out_loc')->index();
            $table->string('out_product_group');
            $table->string('out_product')->index();
            $table->integer('out_product_id');
            $table->decimal('out_qty', 11, 2);
            $table->string('out_qty_unit');
            $table->decimal('out_qty_alt', 11, 2);
            $table->string('out_qty_unit_alt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outwards');
    }
};
