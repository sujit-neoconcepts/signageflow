<?php

namespace App\Http\Controllers\Admin;

use App\Models\Supplier;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use App\Helpers\Helper;

class SupplierController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'supplier', 'resourceTitle' => 'Suppliers', 'iconPath' => 'M11.94 3C9.75 3.03 8 4.81 8 7C7.94 8.64 7.81 10.47 7.03 11.59C9.71 13.22 12 13 12 13C12 13 14.29 13.22 16.97 11.59C16.12 10.22 15.94 8.54 16 7C16 4.79 14.21 3 12 3H11.94M8.86 13.32C6 13.93 4 15.35 4 17V21H12L9 17H6.5M12 21L13.78 13.81C13.78 13.81 13 14 12 14C11 14 10.22 13.81 10.22 13.81M12 21H20V17C20 15.35 18 13.93 15.14 13.32L17.5 17H15Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:supplier_list', ['only' => ['index', 'show']]);
        $this->middleware('can:supplier_create', ['only' => ['create', 'store']]);
        $this->middleware('can:supplier_import', ['only' => ['importView', 'import']]);
        $this->middleware('can:supplier_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:supplier_delete', ['only' => ['destroy', 'bulkDestroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo = Supplier::formInfo();
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo) {
            $query->where(function ($query) use ($value, $formInfo) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo) {
                    foreach (array_keys($formInfo) as $key) {
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $resourceData = QueryBuilder::for(Supplier::class)
            ->defaultSort(array_keys($formInfo)[0])
            ->allowedSorts(array_merge(array_keys($formInfo), ['id']))
            ->allowedFilters(array_merge(array_keys($formInfo), [$globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (Auth::user()->can('supplier_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (Auth::user()->can('supplier_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        // Add import link to extraMainLinks
        if (Auth::user()->can('supplier_import')) {
            $this->resourceNeo['extraMainLinks'] = [
                [
                    'label' => 'Import',
                    'link' => 'supplier.import',
                    'icon' => 'M14,12L10,8V11H2V13H10V16M20,18V6C20,4.89 19.1,4 18,4H6A2,2 0 0,0 4,6V9H6V6H18V18H6V15H4V18A2,2 0 0,0 6,20H18A2,2 0 0,0 20,18Z'
                ]
            ];
        }

        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo) {
            $table->withGlobalSearch();
            //$table->column('id', 'ID', sortable: true);
            foreach (array_keys($formInfo) as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: true, sortable: true);
            }
            $table
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = Supplier::formInfo();
        return Inertia::render('Admin/AddEditView', compact('resourceNeo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $formInfo = Supplier::formInfo();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }
        $request->validate($validateRule, [], $attributeNames);

        // Properly capitalize first character with null checking and trimming
        if (!empty($savedArray['sp_name'])) {
            $savedArray['sp_name'] = Helper::ucfirstlower($savedArray['sp_name']);
        }
        if (!empty($savedArray['sp_addr'])) {
            $savedArray['sp_addr'] = Helper::ucfirstlower($savedArray['sp_addr']);
        }

        Supplier::create($savedArray);

        \ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[0]}]);

        return redirect()->route('supplier.index')->with(['message' => $this->resourceNeo['resourceTitle'] . ' Created Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Supplier $supplier)
    {
        $formdata = $supplier;
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = Supplier::formInfo();
        return Inertia::render('Admin/AddEditView', compact('formdata', 'resourceNeo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        $formInfo = Supplier::formInfo();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        $validateRule['sp_name'] = 'required|unique:suppliers,sp_name,' . $supplier->id;
        $request->validate($validateRule, [], $attributeNames);
        foreach (array_keys($formInfo) as $key) {
            $supplier->{$key} = $request->{$key};
        }

        $supplier->save();

        \ActivityLog::add(['action' => 'updated', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[0]}]);

        return redirect()->route('supplier.index')->with(['message' => $this->resourceNeo['resourceTitle'] . ' Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $formInfo = Supplier::formInfo();
        $uname = $supplier->{array_keys($formInfo)[0]};
        $supplier->delete();

        \ActivityLog::add(['action' => 'deleted', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $uname]);
        return redirect()->back()->with('message', $this->resourceNeo['resourceTitle'] . ' Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        Supplier::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : $uname = implode(',', request('ids'));
        \ActivityLog::add(['action' => 'deleted', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected ' . $this->resourceNeo['resourceTitle'] . ' Deleted !!');
    }

    /**
     * Show the import view.
     */
    public function importView()
    {
        $this->resourceNeo['extraMainLinks'] = [
            [
                'link' => 'supplier.index',
                'label' => 'Back to List',
                'icon' => 'M11.94 3C9.75 3.03 8 4.81 8 7C7.94 8.64 7.81 10.47 7.03 11.59C9.71 13.22 12 13 12 13C12 13 14.29 13.22 16.97 11.59C16.12 10.22 15.94 8.54 16 7C16 4.79 14.21 3 12 3H11.94M8.86 13.32C6 13.93 4 15.35 4 17V21H12L9 17H6.5M12 21L13.78 13.81C13.78 13.81 13 14 12 14C11 14 10.22 13.81 10.22 13.81M12 21H20V17C20 15.35 18 13.93 15.14 13.32L17.5 17H15Z'
            ]
        ];

        $sampleData = [
            ['sp_name' => 'ABC Steel Ltd', 'sp_addr' => '123 Industrial Area', 'sp_phn' => '9876543210', 'sp_email' => 'contact@abcsteel.com', 'sp_gst' => '27ABCDE1234F1Z5'],
            ['sp_name' => 'XYZ Materials', 'sp_addr' => '456 Business Park', 'sp_phn' => '9876543211', 'sp_email' => 'info@xyzmaterials.com', 'sp_gst' => '29XYZAB5678G2W6'],
            ['sp_name' => 'Steel Works Inc', 'sp_addr' => '789 Manufacturing Hub', 'sp_phn' => '9876543212', 'sp_email' => 'sales@steelworks.com', 'sp_gst' => '24STEEL9012H3X7'],
            ['sp_name' => 'Metal Suppliers Co', 'sp_addr' => '321 Trade Center', 'sp_phn' => '9876543213', 'sp_email' => 'orders@metalsuppliers.com', 'sp_gst' => '33METAL3456I4Y8'],
        ];

        $resourceNeo = $this->resourceNeo;

        return Inertia::render('Admin/ImportView', compact('resourceNeo', 'sampleData'));
    }

    /**
     * Import data from a file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048' // 2MB limit
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        // Check if file is readable
        if (!is_readable($path)) {
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! Unable to read the uploaded file.',
                    'msg_type' => 'danger'
                ]);
        }

        $records = array_map('str_getcsv', file($path));

        // Check if file has content
        if (empty($records)) {
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! The uploaded file is empty.',
                    'msg_type' => 'danger'
                ]);
        }

        // Remove header row
        $headers = array_shift($records);

        // Validate headers
        $expectedHeaders = ['sp_name', 'sp_addr', 'sp_phn', 'sp_email', 'sp_gst'];
        $allowedHeaders = $expectedHeaders;

        $missingHeaders = array_diff($expectedHeaders, $headers);

        if (!empty($missingHeaders)) {
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! Missing required columns: ' . implode(', ', $missingHeaders),
                    'msg_type' => 'danger'
                ]);
        }

        // Check if there are data rows
        if (empty($records)) {
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! No data rows found in the file.',
                    'msg_type' => 'danger'
                ]);
        }

        // Validate all records first
        $errors = [];
        $rowNumber = 2; // Starting from 2 as row 1 is headers
        $validatedData = [];
        $formInfo = Supplier::formInfo();

        foreach ($records as $record) {
            // Skip empty rows
            if (empty(array_filter($record))) {
                $rowNumber++;
                continue;
            }

            $data = array_combine($headers, $record);

            // Handle empty values - convert empty strings to null for proper database storage
            foreach ($data as $key => $value) {
                if ($value === '') {
                    $data[$key] = null;
                }
            }

            // Build validation rules using the same logic as store method
            $attributeNames = [];
            $validationRules = [];
            $savedArray = [];

            foreach (array_keys($formInfo) as $key) {
                $attributeNames[$key] = $formInfo[$key]['label'];
                if (isset($formInfo[$key]['vRule'])) {
                    $validationRules[$key] = $formInfo[$key]['vRule'];
                }
                $savedArray[$key] = $data[$key];
            }

            // Create validator with same rules as store function
            $validator = \Validator::make($savedArray, $validationRules, [], $attributeNames);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
            } else {
                $validatedData[] = $savedArray;
            }

            $rowNumber++;
        }

        // If there are any validation errors, redirect back with errors
        if (!empty($errors)) {
            $errorMessage = "Import failed! Please fix the following errors:\n\n" . implode("\n", $errors);
            return redirect()->back()
                ->with([
                    'message' => $errorMessage,
                    'msg_type' => 'danger'
                ]);
        }

        // If validation passed for all records, proceed with import using transaction
        try {
            \DB::beginTransaction();

            foreach ($validatedData as $data) {
                // Properly capitalize first character with null checking and trimming
                if (!empty($data['sp_name'])) {
                    $data['sp_name'] = Helper::ucfirstlower($data['sp_name']);
                }
                if (!empty($data['sp_name'])) {
                    $data['sp_name'] = Helper::ucfirstlower($data['sp_name']);
                }
                if (!empty($data['sp_addr'])) {
                    $data['sp_addr'] = Helper::ucfirstlower($data['sp_addr']);
                }
                Supplier::create($data);
            }

            \DB::commit();
            \ActivityLog::add(['action' => 'imported', 'module' => 'tubeLength', 'data_key' => count($validatedData)]);

            return redirect()->route('supplier.index')
                ->with([
                    'message' => count($validatedData) . ' supplier records imported successfully!',
                    'msg_type' => 'success'
                ]);
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Supplier import failed: ' . $e->getMessage());
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! An unexpected error occurred while importing the data. Please try again.',
                    'msg_type' => 'danger'
                ]);
        }
    }
}
