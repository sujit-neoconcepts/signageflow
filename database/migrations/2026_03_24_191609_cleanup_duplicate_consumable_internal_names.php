<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = \DB::table('consumable_internal_names')
            ->select('name', \DB::raw('count(*) as count'))
            ->groupBy('name')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = \DB::table('consumable_internal_names')
                ->where('name', $duplicate->name)
                ->orderBy('id', 'asc')
                ->pluck('id');
            
            $keepId = $ids->first();
            $removeIds = $ids->slice(1);
            
            \DB::table('consumable_internal_names')
                ->whereIn('id', $removeIds)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as original data is lost
    }
};
