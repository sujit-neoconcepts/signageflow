<?php

namespace App\Http\Controllers\Admin;

use App\Models\Purchase;
use App\Models\ConsumableInternalName;
use App\Models\Product;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Helpers\ActivityLog;
use Exception;

class ConsumableInternalNameController extends Controller
{
    protected $resourceNeo = [
        'resourceName' => 'consumableInternalName',
        'resourceTitle' => 'Product Internal Name',
        'iconPath' => 'M11.5 9C11.5 7.62 12.62 6.5 14 6.5C15.1 6.5 16.03 7.21 16.37 8.19C16.45 8.45 16.5 8.72 16.5 9C16.5 10.38 15.38 11.5 14 11.5C12.91 11.5 12 10.81 11.64 9.84C11.55 9.58 11.5 9.29 11.5 9M5 9C5 13.5 10.08 19.66 11 20.81L10 22C10 22 3 14.25 3 9C3 5.83 5.11 3.15 8 2.29C6.16 3.94 5 6.33 5 9M14 2C17.86 2 21 5.13 21 9C21 14.25 14 22 14 22C14 22 7 14.25 7 9C7 5.13 10.14 2 14 2M14 4C11.24 4 9 6.24 9 9C9 10 9 12 14 18.71C19 12 19 10 19 9C19 6.24 16.76 4 14 4Z',
        'actions' => ['c', 'r', 'u', 'd']
    ];

    public function __construct()
    {
        $this->middleware('can:consumableInternalName_list', ['only' => ['index', 'show']]);
        $this->middleware('can:consumableInternalName_create', ['only' => ['create', 'store', 'importView', 'import']]);
        $this->middleware('can:consumableInternalName_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:consumableInternalName_edit', ['only' => ['edit', 'update']]);
    }

    public function index()
    {
        $formInfo = ConsumableInternalName::formInfo();
        $formInfoMulti = [];
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    foreach (array_keys($formInfo) as $key) {
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $resourceData = QueryBuilder::for(ConsumableInternalName::class)
            ->defaultSort('name')
            ->allowedSorts(array_keys($formInfo))
            ->allowedFilters(array_merge(array_keys($formInfo), [$globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (Auth::user()->can('consumableInternalName_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (Auth::user()->can('consumableInternalName_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        $this->resourceNeo['extraMainLinks'] = [
            [
                'label' => 'Import',
                'link' => 'consumableInternalName.import',
                'icon' => 'M14,12L10,8V11H2V13H10V16M20,18V6C20,4.89 19.1,4 18,4H6A2,2 0 0,0 4,6V9H6V6H18V18H6V15H4V18A2,2 0 0,0 6,20H18A2,2 0 0,0 20,18Z'
            ],
            [
                'label' => 'Sync',
                'link' => 'consumableInternalName.sync',
                'icon' => 'M12,18A6,6 0 0,1 6,12C6,11 6.25,10.03 6.7,9.2L5.24,7.74C4.46,8.97 4,10.43 4,12A8,8 0 0,0 12,20V23L16,19L12,15V18M12,4V1L8,5L12,9V6A6,6 0 0,1 18,12C18,13 17.75,13.97 17.3,14.8L18.76,16.26C19.54,15.03 20,13.57 20,12A8,8 0 0,0 12,4Z'
            ]
        ];
        $this->resourceNeo['showall'] = true;
        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' =>
        $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo) {
            $table->withGlobalSearch();
            foreach (array_keys($formInfo) as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false);
            }
            $table->column(label: 'Actions')->perPageOptions([10, 15, 30, 50, 100]);
        });
    }

    public function create()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = ConsumableInternalName::formInfo();
        return Inertia::render('Admin/AddEditView', compact('resourceNeo'));
    }

    public function store(Request $request)
    {
        $formInfo = ConsumableInternalName::formInfo();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }
        
        if (isset($savedArray['name'])) {
            $savedArray['name'] = trim($savedArray['name']);
            $request->merge(['name' => $savedArray['name']]);
        }
        
        $request->validate($validateRule, [], $attributeNames);
        $savedArray['openStockUnit'] = $this->normalizeOpenStockUnit($request->openStockUnit);
        $internalName = ConsumableInternalName::create($savedArray);

        ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->name]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Created successfully', 
                'data' => [
                    'id' => $internalName->id, // or mapped id dynamically 
                    'label' => $internalName->name,
                    'data' => [
                        'unitName' => $internalName->unitName,
                        'unitAltName' => $internalName->unitAltName
                    ]
                ]
            ]);
        }

        return redirect()->route('consumableInternalName.index')->with(['message' => 'Consumable Internal Name Created Successfully !!', 'msg_type' => 'info']);
    }

    public function edit(ConsumableInternalName $consumableInternalName)
    {
        $formdata = $consumableInternalName;
        $mode = $this->normalizeOpenStockUnit($consumableInternalName->openStockUnit);
        $formdata->openStockUnit = ['id' => $mode, 'label' => $this->openStockUnitLabel($mode)];
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = ConsumableInternalName::formInfo();
        return Inertia::render('Admin/AddEditView', compact('formdata', 'resourceNeo'));
    }

    public function update(Request $request, ConsumableInternalName $consumableInternalName)
    {
        $formInfo = ConsumableInternalName::formInfo();
        $attributeNames = [];
        $validateRule = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        
        if ($request->has('name')) {
            $request->merge(['name' => trim($request->name)]);
        }
        
        $validateRule['name'] = 'required|unique:consumable_internal_names,name,' . $consumableInternalName->id;
        $request->validate($validateRule, [], $attributeNames);

        $savedArray = [];
        foreach (array_keys($formInfo) as $key) {
            $savedArray[$key] = $request->{$key};
        }
        $savedArray['openStockUnit'] = $this->normalizeOpenStockUnit($request->openStockUnit);
        $consumableInternalName->update($savedArray);

        return redirect()->route('consumableInternalName.index')->with(['message' => 'Consumable Internal Name Updated Successfully !!', 'msg_type' => 'info']);
    }

    public function destroy(ConsumableInternalName $consumableInternalName)
    {
        $name = $consumableInternalName->name;
        $consumableInternalName->delete();
        ActivityLog::add(['action' => 'deleted', 'module' => 'consumableInternalName', 'data_key' => $name]);
        return redirect()->back()->with('message', 'Consumable Internal Name Deleted !!');
    }

    public function bulkDestroy()
    {
        ConsumableInternalName::whereIn('id', request('ids'))->delete();
        $name = (count(request('ids')) > 50) ? 'Many' : implode(',', request('ids'));
        ActivityLog::add(['action' => 'deleted', 'module' => 'consumableInternalName', 'data_key' => $name]);
        return redirect()->back()->with('message', 'Selected Items Deleted !!');
    }

    public function sync()
    {
        $products = Product::query()
            ->whereNotNull('pr_detail_int')
            ->where('pr_detail_int', '!=', '')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('pr_detail_int');

        $count = 0;
        foreach ($products as $product) {
            $name = trim($product->pr_detail_int);
            if ($name === '') continue;
            
            $exists = ConsumableInternalName::where('name', $name)->exists();
            if (!$exists) {
                $latestPurchase = Purchase::where('pur_pr_detail_int', $product->pr_detail_int)
                    ->orderBy('pur_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();
                
                // Double check to prevent rare race condition if sync() is multi-threaded? 
                // Using a lock or transaction is better but for this case, another exists() check might suffice if some rows were added.
                // But given we are deduplicating the Collection, we just need to ensure we don't add what's already in DB.
                
                try {
                    ConsumableInternalName::create([
                        'name' => $name,
                        'unitPrice' => $latestPurchase ? $latestPurchase->pur_rate_int : 0,
                        'unitName' => $product->pr_int_unit ?? '',
                        'unitAltName' => $product->pr_int_unit_alt ?? '',
                        'openStockUnit' => 0,
                        'openStockMarginPercent' => 0,
                    ]);
                    $count++;
                } catch (Exception $e) {
                    // Fail gracefully if someone else inserted it between our check and create
                    continue;
                }
            }
        }

        return redirect()->back()->with(['message' => "$count Records Synced Successfully !!", 'msg_type' => 'success']);
    }

    public function importView()
    {
        $this->resourceNeo['extraMainLinks'] = [
            [
                'link' => 'consumableInternalName.index',
                'label' => 'Back to List',
                'icon' => 'M11.5 9C11.5 7.62 12.62 6.5 14 6.5C15.1 6.5 16.03 7.21 16.37 8.19C16.45 8.45 16.5 8.72 16.5 9C16.5 10.38 15.38 11.5 14 11.5C12.91 11.5 12 10.81 11.64 9.84C11.55 9.58 11.5 9.29 11.5 9M5 9C5 13.5 10.08 19.66 11 20.81L10 22C10 22 3 14.25 3 9C3 5.83 5.11 3.15 8 2.29C6.16 3.94 5 6.33 5 9M14 2C17.86 2 21 5.13 21 9C21 14.25 14 22 14 22C14 22 7 14.25 7 9C7 5.13 10.14 2 14 2M14 4C11.24 4 9 6.24 9 9C9 10 9 12 14 18.71C19 12 19 10 19 9C19 6.24 16.76 4 14 4Z'
            ]
        ];

        $sampleData = [
            ['name' => 'Item A', 'unitPrice' => '100', 'unitName' => 'Kg', 'unitAltName' => 'Packet', 'openStockUnit' => '0', 'openStockMarginPercent' => '0'],
            ['name' => 'Item B', 'unitPrice' => '200', 'unitName' => 'Ltr', 'unitAltName' => 'Bottle', 'openStockUnit' => '1', 'openStockMarginPercent' => '2.5'],
        ];

        $resourceNeo = $this->resourceNeo;

        return Inertia::render('Admin/ImportView', compact('resourceNeo', 'sampleData'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048'
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        if (!is_readable($path)) {
            return redirect()->back()->with(['message' => 'Import failed! Unable to read file.', 'msg_type' => 'danger']);
        }

        $records = array_map('str_getcsv', file($path));
        if (empty($records)) {
            return redirect()->back()->with(['message' => 'Import failed! File is empty.', 'msg_type' => 'danger']);
        }

        $headers = array_shift($records);
        $expectedHeaders = ['name', 'unitPrice', 'unitName', 'unitAltName', 'openStockUnit', 'openStockMarginPercent'];
        
        $requiredHeaders = ['name', 'unitPrice', 'unitName'];
        $missingHeaders = array_diff($requiredHeaders, $headers);
        if (!empty($missingHeaders)) {
            return redirect()->back()->with(['message' => 'Import failed! Missing columns: ' . implode(', ', $missingHeaders), 'msg_type' => 'danger']);
        }

        $validatedData = [];
        $errors = [];
        $rowNumber = 2;
        $seenNames = [];

        foreach ($records as $record) {
            if (empty(array_filter($record))) {
                $rowNumber++;
                continue;
            }
            if (count($record) < count($headers)) {
                $record = array_pad($record, count($headers), null);
            }
            
            $data = array_combine($headers, $record);
            if (isset($data['name'])) {
                $data['name'] = trim($data['name']);
            }
            
            // Check for duplicates within the current CSV file
            if (isset($data['name']) && isset($seenNames[$data['name']])) {
                $errors[] = "Row {$rowNumber}: Duplicate name '{$data['name']}' within the file.";
                $rowNumber++;
                continue;
            }
            if (isset($data['name'])) {
                $seenNames[$data['name']] = true;
            }
            
            $validator = Validator::make($data, [
                'name' => 'required|unique:consumable_internal_names,name',
                'unitPrice' => 'required|numeric',
                'unitName' => 'required',
                'unitAltName' => 'nullable',
                'openStockUnit' => 'nullable|in:0,1',
                'openStockMarginPercent' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
            } else {
                $validatedData[] = $validator->validated();
            }
            $rowNumber++;
        }

        if (!empty($errors)) {
            return redirect()->back()->with(['message' => implode("\n", $errors), 'msg_type' => 'danger']);
        }

        try {
            DB::beginTransaction();
            foreach ($validatedData as $data) {
                $data['openStockUnit'] = $this->normalizeOpenStockUnit($data['openStockUnit'] ?? 0);
                $data['openStockMarginPercent'] = $data['openStockMarginPercent'] ?? 0;
                ConsumableInternalName::create($data);
            }
            DB::commit();
            ActivityLog::add(['action' => 'imported', 'module' => 'consumableInternalName', 'data_key' => count($validatedData)]);
            return redirect()->route('consumableInternalName.index')->with(['message' => count($validatedData) . ' records imported!', 'msg_type' => 'success']);
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->with(['message' => 'Import failed: ' . $e->getMessage(), 'msg_type' => 'danger']);
        }
    }

    protected function normalizeOpenStockUnit($openStockUnit): int
    {
        if (is_array($openStockUnit)) {
            $openStockUnit = $openStockUnit['id'] ?? 0;
        }

        return ((int) $openStockUnit) === 1 ? 1 : 0;
    }

    protected function openStockUnitLabel(int $mode): string
    {
        return $mode === 1 ? 'Alternative=>1' : 'Main=>0';
    }

    public function options()
    {
        $options = ConsumableInternalName::select('id', 'name', 'unitName', 'unitAltName', 'unitPrice')->orderBy('name')->get();
        return response()->json($options);
    }
}
