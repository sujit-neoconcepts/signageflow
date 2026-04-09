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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->date('exp_date');
            $table->decimal('amount', 11, 3);
            $table->string('doneby')->nullable();
            $table->string('exp_cate');
            $table->string('details')->nullable();
            $table->string('job_details')->nullable();
            $table->string('incharge');
            $table->string('amt_type')->default('Expense');
            $table->string('job_no')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
