<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostSheet;
use App\Models\CostSheetComposition;
use Illuminate\Http\Request;

class CostSheetCompositionController extends Controller
{
    public function index(CostSheet $costSheet)
    {
        $compositions = $costSheet->compositions()->with('consumable:id,name,unitName,unitAltName,unitPrice')->get();
        return response()->json($compositions);
    }

    public function store(Request $request, CostSheet $costSheet)
    {
        $data = $request->validate([
            'compositions' => 'array',
            'compositions.*.id' => 'nullable|integer',
            'compositions.*.consumable_internal_name_id' => 'required|exists:consumable_internal_names,id',
            'compositions.*.quantity' => 'required|numeric|min:0',
            'compositions.*.margin' => 'nullable|numeric|min:0',
            'compositions.*.unit' => 'nullable|string'
        ]);

        $idsToKeep = [];

        foreach ($data['compositions'] ?? [] as $compData) {
            $composition = $costSheet->compositions()->updateOrCreate(
                ['id' => $compData['id'] ?? null],
                [
                    'consumable_internal_name_id' => $compData['consumable_internal_name_id'],
                    'quantity' => $compData['quantity'],
                    'margin' => $compData['margin'] ?? 0.00,
                    'unit' => $compData['unit'] ?? null,
                ]
            );
            $idsToKeep[] = $composition->id;
        }

        // Delete removed compositions
        $costSheet->compositions()->whereNotIn('id', $idsToKeep)->delete();

        return response()->json([
            'message' => 'Compositions saved successfully.',
            'compositions' => $costSheet->compositions()->with('consumable:id,name,unitName,unitAltName,unitPrice')->get()
        ]);
    }
}
