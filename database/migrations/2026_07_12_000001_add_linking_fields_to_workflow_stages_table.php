<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->boolean('need_enquiry_number')->default(false);
            $table->boolean('need_sales_order_number')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropColumn(['need_enquiry_number', 'need_sales_order_number']);
        });
    }
};
