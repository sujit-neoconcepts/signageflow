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
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_no')->unique();
            $table->string('enquiry_prefix', 2);
            $table->unsignedInteger('enquiry_sequence');
            $table->string('enquiry_fy', 4);
            $table->date('enquiry_date')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('product_type');
            $table->text('remark')->nullable();
            
            $table->decimal('transport_charge', 15, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(18);
            $table->decimal('items_taxable_total', 15, 2)->default(0);
            $table->decimal('items_gst_total', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
