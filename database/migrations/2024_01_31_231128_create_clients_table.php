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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('cl_name');
            $table->string('contact_person')->nullable();
            $table->string('cl_addr')->nullable();
            $table->string('cl_addr2')->nullable();
            $table->string('pincode')->nullable();
            $table->string('cl_phn')->nullable();
            $table->string('cl_email')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('cl_gst')->nullable();
            $table->tinyInteger('active')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
