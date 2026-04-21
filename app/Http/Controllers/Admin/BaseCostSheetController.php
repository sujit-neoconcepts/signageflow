<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

abstract class BaseCostSheetController extends Controller
{
    protected array $resourceNeo = [
        'resourceName' => '',
        'resourceTitle' => '',
        'iconPath' => 'M3,3H21V6H3V3M3,8H21V11H3V8M3,13H21V16H3V13M3,18H21V21H3V18Z',
        'actions' => ['c', 'r', 'u', 'd'],
    ];

    public function __construct()
    {
        $permissionKey = $this->permissionKey();
        $this->middleware("can:{$permissionKey}_list", ['only' => ['index', 'show']]);
        $this->middleware("can:{$permissionKey}_create", ['only' => ['create', 'store', 'importView', 'import']]);
        $this->middleware("can:{$permissionKey}_delete", ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware("can:{$permissionKey}_edit", ['only' => ['edit', 'update']]);
    }

    abstract protected function resourceName(): string;
    abstract protected function resourceTitle(): string;
    abstract protected function permissionKey(): string;
    abstract protected function prodType(): string;

    protected function buildResourceNeo(): array
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['resourceName'] = $this->resourceName();
        $resourceNeo['resourceTitle'] = $this->resourceTitle();

        return $resourceNeo;
    }

    public function index()
    {
        $formInfo = CostSheet::formInfo();
        $formInfoMulti = [];

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    $query->orWhere('name', 'LIKE', "%{$value}%");
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;
        $resourceData = QueryBuilder::for(CostSheet::where('prod_type', $this->prodType()))
            ->defaultSort('name')
            ->allowedSorts(array_merge(array_keys($formInfo), array_keys($formInfoMulti)))
            ->allowedFilters([$globalSearch])
            ->paginate($perPage)
            ->withQueryString();

        $resourceNeo = $this->buildResourceNeo();
        if (\Auth::user()->can($this->permissionKey() . '_delete')) {
            $resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }
        if (\Auth::user()->can($this->permissionKey() . '_export')) {
            $resourceNeo['bulkActions']['csvExport'] = [];
        }
        $resourceNeo['extraMainLinks'] = [
            [
                'label' => 'Import',
                'link' => $this->resourceName() . '.import',
                'icon' => 'M14,12L10,8V11H2V13H10V16M20,18V6C20,4.89 19.1,4 18,4H6A2,2 0 0,0 4,6V9H6V6H18V18H6V15H4V18A2,2 0 0,0 6,20H18A2,2 0 0,0 20,18Z',
            ],
        ];

        return Inertia::render('Admin/CostSheetIndexView', compact('resourceData', 'resourceNeo'))->table(function (InertiaTable $table) use ($formInfo, $formInfoMulti) {
            $table->withGlobalSearch();

            foreach (array_keys($formInfo) as $key) {
                $table->column(
                    $key,
                    $formInfo[$key]['label'],
                    searchable: false,
                    sortable: $formInfo[$key]['sortable'] ?? false,
                    hidden: $formInfo[$key]['hidden'] ?? false,
                    extra: ['align' => $formInfo[$key]['align'] ?? 'left']
                );
            }
            foreach (array_keys($formInfoMulti) as $key) {
                $table->column(
                    $key,
                    $formInfoMulti[$key]['label'],
                    searchable: false,
                    sortable: $formInfoMulti[$key]['sortable'] ?? false,
                    hidden: $formInfoMulti[$key]['hidden'] ?? false
                );
            }

            $table->column(label: 'Actions')->perPageOptions([10, 15, 30, 50, 100]);
        });
    }

    public function create()
    {
        $resourceNeo = $this->buildResourceNeo();
        $resourceNeo['formInfo'] = CostSheet::formInfo();

        return Inertia::render('Admin/AddEditView', compact('resourceNeo'));
    }

    public function store(Request $request)
    {
        $formInfo = CostSheet::formInfo();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = ['prod_type' => $this->prodType()];

        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }

        $validateRule['name'] = [
            'required',
            'max:255',
            Rule::unique('cost_sheets', 'name')->where(fn ($q) => $q->where('prod_type', $this->prodType())),
        ];

        $request->validate($validateRule, [], $attributeNames);
        CostSheet::create($savedArray);

        \ActivityLog::add([
            'action' => 'added',
            'module' => $this->resourceName(),
            'data_key' => $request->name,
        ]);

        return redirect()->route($this->resourceName() . '.index')->with([
            'message' => $this->resourceTitle() . ' Created Successfully !!',
            'msg_type' => 'info',
        ]);
    }

    /**
     * Quick store for inline creation from Enquiry/SalesOrder forms.
     * Returns the newly created cost sheet item as JSON.
     */
    public function quickStore(Request $request)
    {
        $prodType = $this->prodType();

        $validated = $request->validate([
            'name'      => ['required', 'max:255', Rule::unique('cost_sheets', 'name')->where(fn ($q) => $q->where('prod_type', $prodType))],
            'no_of_unit' => 'required|integer|min:1',
            'qty_unit'  => 'required|max:100',
            'alt_units' => 'nullable|max:100',
            'rate'      => 'nullable|numeric|min:0',
        ]);

        $item = CostSheet::create([
            'prod_type'  => $prodType,
            'name'       => $validated['name'],
            'no_of_unit' => $validated['no_of_unit'],
            'qty_unit'   => $validated['qty_unit'],
            'alt_units'  => $validated['alt_units'] ?? null,
            'rate'       => $validated['rate'] ?? 0,
        ]);

        \ActivityLog::add([
            'action'   => 'added',
            'module'   => $this->resourceName(),
            'data_key' => $item->name,
        ]);

        return response()->json([
            'id'        => $item->id,
            'label'     => $item->name,
            'name'      => $item->name,
            'prod_type' => $item->prod_type,
            'qty_unit'  => $item->qty_unit,
            'alt_units' => $item->alt_units,
            'rate'      => (float) $item->rate,
        ], 201);
    }


    public function show(CostSheet $costSheet)
    {
    }

    public function edit(CostSheet $costSheet)
    {
        if ($costSheet->prod_type !== $this->prodType()) {
            abort(404);
        }

        $formdata = $costSheet;
        $resourceNeo = $this->buildResourceNeo();
        $resourceNeo['formInfo'] = CostSheet::formInfo();

        return Inertia::render('Admin/AddEditView', compact('formdata', 'resourceNeo'));
    }

    public function update(Request $request, CostSheet $costSheet)
    {
        if ($costSheet->prod_type !== $this->prodType()) {
            abort(404);
        }

        $formInfo = CostSheet::formInfo();
        $attributeNames = [];
        $validateRule = [];

        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }

        $validateRule['name'] = [
            'required',
            'max:255',
            Rule::unique('cost_sheets', 'name')
                ->where(fn ($q) => $q->where('prod_type', $this->prodType()))
                ->ignore($costSheet->id),
        ];

        $request->validate($validateRule, [], $attributeNames);

        foreach (array_keys($formInfo) as $key) {
            $costSheet->{$key} = $request->{$key};
        }

        $costSheet->save();

        \ActivityLog::add([
            'action' => 'updated',
            'module' => $this->resourceName(),
            'data_key' => $request->name,
        ]);

        return redirect()->route($this->resourceName() . '.index')->with([
            'message' => $this->resourceTitle() . ' Updated Successfully !!',
            'msg_type' => 'info',
        ]);
    }

    public function destroy(CostSheet $costSheet)
    {
        if ($costSheet->prod_type !== $this->prodType()) {
            abort(404);
        }

        $uname = $costSheet->id;
        $costSheet->delete();
        \ActivityLog::add([
            'action' => 'deleted',
            'module' => $this->resourceName(),
            'data_key' => $uname,
        ]);

        return redirect()->back()->with('message', $this->resourceTitle() . ' Deleted !!');
    }

    public function bulkDestroy()
    {
        $ids = request('ids', []);
        CostSheet::where('prod_type', $this->prodType())->whereIn('id', $ids)->delete();

        $uname = (count($ids) > 50) ? 'Many' : implode(',', $ids);
        \ActivityLog::add([
            'action' => 'deleted',
            'module' => $this->resourceName(),
            'data_key' => $uname,
        ]);

        return redirect()->back()->with('message', 'Selected ' . $this->resourceTitle() . ' Deleted !!');
    }

    public function importView()
    {
        $resourceNeo = $this->buildResourceNeo();
        $units = \App\Models\Munit::select('name')->orderBy('name')->pluck('name')->values();
        $qtyUnit1 = $units->get(0, '');
        $qtyUnit2 = $units->get(1, $qtyUnit1);
        $altUnit = $units->get(2, $qtyUnit2);

        $resourceNeo['extraMainLinks'] = [
            [
                'link' => $this->resourceName() . '.index',
                'label' => 'Back to List',
                'icon' => 'M12 2L4 5V11C4 16.55 7.84 21.74 13 23C18.16 21.74 22 16.55 22 11V5L12 2M11 18V13H8L13 8V13H16L11 18Z',
            ],
        ];

        $sampleData = [
            ['name' => 'Acrylic', 'qty_unit' => $qtyUnit1, 'alt_units' => $altUnit, 'rate' => '120.00'],
            ['name' => 'MS Frame', 'qty_unit' => $qtyUnit2, 'alt_units' => $qtyUnit1, 'rate' => '85.50'],
        ];

        return Inertia::render('Admin/ImportView', compact('resourceNeo', 'sampleData'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        if (!is_readable($path)) {
            return redirect()->back()->with([
                'message' => 'Import failed! Unable to read the uploaded file.',
                'msg_type' => 'danger',
            ]);
        }

        $records = array_map('str_getcsv', file($path));
        if (empty($records)) {
            return redirect()->back()->with([
                'message' => 'Import failed! The uploaded file is empty.',
                'msg_type' => 'danger',
            ]);
        }

        $headers = array_shift($records);
        $expectedHeaders = ['name', 'qty_unit', 'alt_units', 'rate'];
        $missingHeaders = array_diff($expectedHeaders, $headers);
        if (!empty($missingHeaders)) {
            return redirect()->back()->with([
                'message' => 'Import failed! Missing required columns: ' . implode(', ', $missingHeaders),
                'msg_type' => 'danger',
            ]);
        }

        if (empty($records)) {
            return redirect()->back()->with([
                'message' => 'Import failed! No data rows found in the file.',
                'msg_type' => 'danger',
            ]);
        }

        $errors = [];
        $rowNumber = 2;
        $validatedData = [];
        $allUnits = \App\Models\Munit::pluck('name')->toArray();
        $allUnitsLookup = array_flip($allUnits);

        foreach ($records as $record) {
            if (empty(array_filter($record, fn ($val) => $val !== null && $val !== ''))) {
                $rowNumber++;
                continue;
            }

            $data = array_combine($headers, $record);
            $validator = \Validator::make($data, [
                'name' => ['required', 'max:255'],
                'qty_unit' => 'required',
                'alt_units' => 'nullable',
                'rate' => 'required|numeric|min:0',
            ], [
                'name.required' => 'Name is required',
                'qty_unit.required' => 'Qty Unit is required',
                'rate.required' => 'Rate is required',
                'rate.numeric' => 'Rate must be numeric',
                'rate.min' => 'Rate must be >= 0',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
            } else {
                $rowValidated = $validator->validated();
                if (!isset($allUnitsLookup[$rowValidated['qty_unit']])) {
                    $errors[] = "Row {$rowNumber}: Qty Unit does not exist in Measurement Unit master.";
                }
                if (!empty($rowValidated['alt_units']) && !isset($allUnitsLookup[$rowValidated['alt_units']])) {
                    $errors[] = "Row {$rowNumber}: Alt Units does not exist in Measurement Unit master.";
                }
                $validatedData[] = $rowValidated;
            }

            $rowNumber++;
        }

        if (!empty($errors)) {
            return redirect()->back()->with([
                'message' => "Import failed! Please fix the following errors:\n\n" . implode("\n", $errors),
                'msg_type' => 'danger',
            ]);
        }

        try {
            \DB::beginTransaction();
            $createdCount = 0;
            $updatedCount = 0;

            foreach ($validatedData as $data) {
                $model = CostSheet::updateOrCreate(
                    [
                        'prod_type' => $this->prodType(),
                        'name' => $data['name'],
                    ],
                    [
                        'qty_unit' => $data['qty_unit'],
                        'alt_units' => $data['alt_units'] ?? null,
                        'rate' => $data['rate'],
                    ]
                );

                if ($model->wasRecentlyCreated) {
                    $createdCount++;
                } else {
                    $updatedCount++;
                }
            }

            \DB::commit();
            \ActivityLog::add([
                'action' => 'imported',
                'module' => $this->resourceName(),
                'data_key' => count($validatedData),
            ]);

            return redirect()->route($this->resourceName() . '.index')->with([
                'message' => $this->resourceTitle() . ' import completed. Created: ' . $createdCount . ', Updated: ' . $updatedCount . '.',
                'msg_type' => 'success',
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();

            return redirect()->back()->with([
                'message' => 'Import failed! Error: ' . $e->getMessage(),
                'msg_type' => 'danger',
            ]);
        }
    }
}
