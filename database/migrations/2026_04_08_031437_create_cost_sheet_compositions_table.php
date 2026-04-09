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
        Schema::create('cost_sheet_compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_sheet_id')->constrained('cost_sheets')->onDelete('cascade');
            $table->foreignId('consumable_internal_name_id')->constrained('consumable_internal_names')->onDelete('cascade');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_sheet_compositions');
    }
};
