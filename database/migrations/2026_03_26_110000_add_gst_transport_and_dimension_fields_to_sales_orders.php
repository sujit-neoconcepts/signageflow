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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('transport_charge', 15, 2)->default(0)->after('remark');
            $table->decimal('gst_percent', 5, 2)->default(18)->after('transport_charge');
            $table->decimal('items_taxable_total', 15, 2)->default(0)->after('gst_percent');
            $table->decimal('items_gst_total', 15, 2)->default(0)->after('items_taxable_total');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->string('qty_mode', 20)->default('direct')->after('item_name');
            $table->decimal('length', 15, 4)->nullable()->after('qty_mode');
            $table->decimal('width', 15, 4)->nullable()->after('length');
            $table->decimal('pieces', 15, 4)->nullable()->after('width');
            $table->decimal('taxable_amount', 15, 2)->default(0)->after('line_total');
            $table->decimal('gst_percent', 5, 2)->default(18)->after('taxable_amount');
            $table->decimal('gst_amount', 15, 2)->default(0)->after('gst_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'qty_mode',
                'length',
                'width',
                'pieces',
                'taxable_amount',
                'gst_percent',
                'gst_amount',
            ]);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn([
                'transport_charge',
                'gst_percent',
                'items_taxable_total',
                'items_gst_total',
            ]);
        });
    }
};
