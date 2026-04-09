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
        Schema::create('open_stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('txn_date')->index();
            $table->string('transaction_type')->index();
            $table->string('internal_name')->index();
            $table->string('location')->index();
            $table->string('incharge')->index();
            $table->string('open_stock_unit');
            $table->decimal('qty', 15, 4);
            $table->decimal('base_unit_price', 15, 4)->default(0);
            $table->decimal('margin_percent', 8, 2)->default(0);
            $table->decimal('effective_unit_price', 15, 4)->default(0);
            $table->decimal('line_amount', 15, 2)->default(0);
            $table->string('source_type')->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->unsignedBigInteger('source_item_id')->nullable()->index();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'open_stock_transactions_source_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_stock_transactions');
    }
};
