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
        Schema::create('enquiry_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enquiry_id');
            $table->unsignedBigInteger('cost_sheet_id');
            $table->string('item_name');
            $table->string('qty_mode', 20)->default('direct');
            $table->decimal('length', 15, 4)->nullable();
            $table->decimal('width', 15, 4)->nullable();
            $table->decimal('pieces', 15, 4)->nullable();
            $table->decimal('qty', 15, 4);
            $table->decimal('rate', 15, 4);
            $table->decimal('line_total', 15, 2);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(18);
            $table->decimal('gst_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('enquiry_id')->references('id')->on('enquiries')->onDelete('cascade');
            $table->foreign('cost_sheet_id')->references('id')->on('cost_sheets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enquiry_items');
    }
};
