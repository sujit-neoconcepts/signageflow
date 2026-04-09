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
        Schema::create('cost_sheets', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('prod_type')->index();
            $table->string('name');
            $table->string('qty_unit');
            $table->string('alt_units')->nullable();
            $table->decimal('rate', 15, 2)->default(0);
            $table->unique(['prod_type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_sheets');
    }
};
