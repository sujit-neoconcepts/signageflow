<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add status to enquiries
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('status')->default('open')->after('total_amount');
            $table->index('status');
        });

        // Add enquiry_id FK to sales_orders
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('enquiry_id')->nullable()->after('client_id');
            $table->foreign('enquiry_id')->references('id')->on('enquiries')->onDelete('set null');
            $table->index('enquiry_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['enquiry_id']);
            $table->dropIndex(['enquiry_id']);
            $table->dropColumn('enquiry_id');
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
