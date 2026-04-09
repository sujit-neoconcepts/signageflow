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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->date('order_date')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('project_name');
            $table->string('product_name');
            $table->text('remark')->nullable();
            $table->decimal('subtotal_base', 15, 2)->default(0);
            $table->decimal('subtotal_effective', 15, 2)->default(0);
            $table->decimal('custom_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
