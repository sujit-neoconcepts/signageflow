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
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id');
            $table->string('internal_name')->index();
            $table->string('location')->index();
            $table->string('incharge')->index();
            $table->decimal('qty', 15, 4);
            $table->string('open_stock_unit');
            $table->decimal('base_unit_price', 15, 4)->default(0);
            $table->decimal('margin_percent', 8, 2)->default(0);
            $table->decimal('effective_unit_price', 15, 4)->default(0);
            $table->decimal('base_total', 15, 2)->default(0);
            $table->decimal('effective_total', 15, 2)->default(0);
            $table->unsignedBigInteger('open_stock_transaction_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
