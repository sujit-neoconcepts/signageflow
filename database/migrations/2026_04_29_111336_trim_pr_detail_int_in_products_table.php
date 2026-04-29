<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('products')->update(['pr_detail_int' => DB::raw('TRIM(pr_detail_int)')]);
        DB::table('purchases')->update(['pur_pr_detail_int' => DB::raw('TRIM(pur_pr_detail_int)')]);
        DB::table('outwards')->update(['out_product' => DB::raw('TRIM(out_product)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
