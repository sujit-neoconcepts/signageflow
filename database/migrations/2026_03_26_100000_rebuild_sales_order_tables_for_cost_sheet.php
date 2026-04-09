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
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sales_order_custom_items');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::enableForeignKeyConstraints();

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->string('order_prefix', 2)->index();
            $table->unsignedInteger('order_sequence');
            $table->string('order_fy', 4)->index();
            $table->date('order_date')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('product_type')->index();
            $table->text('remark')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['order_prefix', 'order_fy', 'order_sequence'], 'sales_order_prefix_fy_sequence_unique');
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('cost_sheet_id');
            $table->string('item_name');
            $table->decimal('qty', 15, 4);
            $table->decimal('rate', 15, 4)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
            $table->foreign('cost_sheet_id')->references('id')->on('cost_sheets')->restrictOnDelete();
        });

        Schema::create('sales_order_custom_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id');
            $table->string('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sales_order_custom_items');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::enableForeignKeyConstraints();

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

            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });

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

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
        });

        Schema::create('sales_order_custom_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id');
            $table->string('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
        });
    }
};
