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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('pr_detail');
            $table->string('pr_hsn');
            $table->string('pr_detail_int')->nullable()->index();
            $table->string('pr_pur_unit')->nullable();
            $table->string('pr_pur_unit_alt')->nullable();
            $table->string('pr_int_unit')->nullable();
            $table->string('pr_int_unit_alt')->nullable();
            $table->decimal('pr_gst_rate', 11, 2)->nullable();
            $table->decimal('pr_min_unit', 19, 8);
            $table->decimal('pr_min_unit_alt', 11, 2)->nullable();
            $table->string('pr_group')->nullable();
            $table->string('groupinfo')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
