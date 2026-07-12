<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('enquiry_no')->nullable();
            $table->string('sales_order_no')->nullable();
            $table->boolean('need_enquiry_number')->default(false);
            $table->boolean('need_sales_order_number')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'enquiry_no',
                'sales_order_no',
                'need_enquiry_number',
                'need_sales_order_number'
            ]);
        });
    }
};
