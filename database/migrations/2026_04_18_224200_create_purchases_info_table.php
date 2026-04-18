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
        Schema::create('purchases_info', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('pur_inv')->index();
            $table->date('pur_date')->index();
            $table->date('received_date')->nullable()->index();
            $table->string('pur_supplier')->nullable()->index();
            $table->decimal('sum_total', 14, 2)->default(0);
            $table->unique('pur_inv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases_info');
    }
};
