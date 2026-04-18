<?php

namespace Database\Seeders;

use App\Models\PurchaseInfo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseInfoBackfillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('purchases') || !Schema::hasTable('purchases_info')) {
            return;
        }

        $groups = DB::table('purchases')
            ->where('entry_type', 0)
            ->whereNotNull('pur_inv')
            ->where('pur_inv', '!=', '')
            ->select('pur_inv')
            ->groupBy('pur_inv')
            ->get();

        foreach ($groups as $group) {
            $sample = DB::table('purchases')
                ->where('entry_type', 0)
                ->where('pur_inv', $group->pur_inv)
                ->orderBy('id')
                ->first();

            if (!$sample) {
                continue;
            }

            $sumTotal = (float) DB::table('purchases')
                ->where('entry_type', 0)
                ->where('pur_inv', $group->pur_inv)
                ->sum('pur_amnt_total');

            $purchaseInfo = PurchaseInfo::updateOrCreate(
                [
                    'pur_inv' => $group->pur_inv,
                ],
                [
                    'pur_date' => $sample->pur_date,
                    'received_date' => $sample->received_date ?? $sample->pur_date,
                    'pur_supplier' => $sample->pur_supplier,
                    'sum_total' => $sumTotal,
                ]
            );

            DB::table('purchases')
                ->where('entry_type', 0)
                ->where('pur_inv', $group->pur_inv)
                ->update([
                    'purchase_info_id' => $purchaseInfo->id,
                ]);
        }
    }
}
