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
        Schema::create('open_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->string('internal_name')->index();
            $table->string('location')->index();
            $table->string('incharge')->index();
            $table->string('open_stock_unit');
            $table->decimal('qty', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['internal_name', 'location', 'incharge'], 'open_stock_balances_unique_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_stock_balances');
    }
};
