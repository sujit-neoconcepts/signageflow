<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostSheet;
use App\Models\CostSheetComposition;
use Illuminate\Http\Request;

class CostSheetCompositionController extends Controller
{
    private array $validSections = ['raw_material', 'signage', 'cabinet', 'letters'];

    public function index(CostSheet $costSheet)
    {
        $compositions = $costSheet->compositions()
            ->with([
                'consumable:id,name,unitName,unitAltName,unitPrice,openStockMarginPercent',
                'childCostSheet:id,name,qty_unit,alt_units,rate,no_of_unit,prod_type',
            ])
            ->get();

        return response()->json($compositions);
    }

    public function store(Request $request, CostSheet $costSheet)
    {
        $data = $request->validate([
            'compositions'           => 'array',
            'total_cost'             => 'nullable|numeric|min:0',
            'compositions.*.id'      => 'nullable|integer',
            'compositions.*.section' => 'required|in:raw_material,signage,cabinet,letters',
            'compositions.*.consumable_internal_name_id' => 'nullable|exists:consumable_internal_names,id',
            'compositions.*.child_cost_sheet_id'         => 'nullable|exists:cost_sheets,id',
            'compositions.*.quantity' => 'required|numeric|min:0',
            'compositions.*.margin'   => 'nullable|numeric|min:0',
            'compositions.*.unit'     => 'nullable|string',
        ]);

        $idsToKeep = [];

        foreach ($data['compositions'] ?? [] as $compData) {
            $payload = [
                'section'                    => $compData['section'],
                'consumable_internal_name_id'=> $compData['consumable_internal_name_id'] ?? null,
                'child_cost_sheet_id'        => $compData['child_cost_sheet_id'] ?? null,
                'quantity'                   => $compData['quantity'],
                'margin'                     => $compData['margin'] ?? 0.00,
                'unit'                       => $compData['unit'] ?? null,
            ];

            $composition = $costSheet->compositions()->updateOrCreate(
                ['id' => $compData['id'] ?? null],
                $payload
            );
            $idsToKeep[] = $composition->id;
        }

        // Delete removed compositions
        $costSheet->compositions()->whereNotIn('id', $idsToKeep)->delete();

        // Update parent total cost (calculated dynamically now)
        // $costSheet->update(['total_cost' => $data['total_cost']]);

        return response()->json([
            'message'      => 'Compositions saved successfully.',
            'compositions' => $costSheet->compositions()
                ->with([
                    'consumable:id,name,unitName,unitAltName,unitPrice,openStockMarginPercent',
                    'childCostSheet:id,name,qty_unit,alt_units,rate,no_of_unit,prod_type',
                ])->get(),
        ]);
    }

    /**
     * Return cost sheets by prod_type for use as child composition items.
     */
    public function costSheetOptions(Request $request)
    {
        $prodType = $request->query('prod_type');
        $query    = CostSheet::select('id', 'name', 'qty_unit', 'alt_units', 'rate', 'no_of_unit', 'prod_type')
            ->with([
                'compositions.consumable:id,name,unitName,unitAltName,unitPrice,openStockMarginPercent',
                'compositions.childCostSheet:id,name,qty_unit,alt_units,rate,no_of_unit,prod_type'
            ])
            ->orderBy('name');

        if ($prodType) {
            $query->where('prod_type', $prodType);
        }

        return response()->json($query->get());
    }
}
