<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use App\Models\Outward;
use App\Models\Pgroup;
use App\Models\Purchase;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;


class ProductController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'product', 'resourceTitle' => 'Product Master', 'iconPath' => 'M18.6,6.62C17.16,6.62 15.8,7.18 14.83,8.15L7.8,14.39C7.16,15.03 6.31,15.38 5.4,15.38C3.53,15.38 2,13.87 2,12C2,10.13 3.53,8.62 5.4,8.62C6.31,8.62 7.16,8.97 7.84,9.65L8.97,10.65L10.5,9.31L9.22,8.2C8.2,7.18 6.84,6.62 5.4,6.62C2.42,6.62 0,9.04 0,12C0,14.96 2.42,17.38 5.4,17.38C6.84,17.38 8.2,16.82 9.17,15.85L16.2,9.61C16.84,8.97 17.69,8.62 18.6,8.62C20.47,8.62 22,10.13 22,12C22,13.87 20.47,15.38 18.6,15.38C17.7,15.38 16.84,15.03 16.16,14.35L15,13.34L13.5,14.68L14.78,15.8C15.8,16.81 17.15,17.37 18.6,17.37C21.58,17.37 24,14.96 24,12C24,9 21.58,6.62 18.6,6.62Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:product_list', ['only' => ['index', 'show']]);
        $this->middleware('can:product_create', ['only' => ['create', 'store', 'importView', 'import']]);
        $this->middleware('can:product_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:product_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:product_delete', ['only' => ['destroy']]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo = Product::formInfo();
        $formInfoMulti = [];
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    /*foreach (array_merge(array_keys($formInfo), array_keys($formInfoMulti)) as $key) {
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }*/
                    $query->orWhere('pr_detail_int', 'LIKE', "%{$value}%");
                    $query->orWhere('pgroups.name', 'LIKE', "%{$value}%");
                   // $query->orWhere('pgroups.sgroup', 'LIKE', "%{$value}%");
                });
            });
        });
        $query = Product::select('products.*', 'pgroups.name as groupinfo_name', 'pgroups.sgroup as groupinfo_sname', 'cin.id as cin_id', 'cin.unitName as cin_unitName', 'cin.unitAltName as cin_unitAltName')
            ->selectRaw("(CASE 
                WHEN products.pr_detail_int IS NULL OR products.pr_detail_int = '' THEN 0
                WHEN cin.id IS NULL THEN 0
                WHEN products.pr_int_unit != cin.unitName THEN 0
                WHEN IFNULL(products.pr_int_unit_alt, '') != IFNULL(cin.unitAltName, '') THEN 0
                ELSE 1
            END) as validation_status_raw")
            ->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo', 'left')
            ->leftJoin('consumable_internal_names as cin', 'cin.name', '=', 'products.pr_detail_int');
        $perPage = request()->query('perPage') ?? 10;
        $resourceData = QueryBuilder::for($query)
            ->defaultSort('pr_detail')
            ->allowedSorts(array_merge(array_keys($formInfo), array_keys($formInfoMulti), ['groupinfo_name', 'groupinfo_sname', AllowedSort::field('validation', 'validation_status_raw')]))
            ->allowedFilters(array_merge(
                array_diff(array_merge(array_keys($formInfo), array_keys($formInfoMulti)), ['groupinfo']), 
                [AllowedFilter::exact('groupinfo'), AllowedFilter::exact('groupinfo_sname', 'pgroups.sgroup'), $globalSearch]
            ))
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($product) {
                $errors = [];
                if (empty($product->pr_detail_int)) {
                    $errors[] = "Internal Name is empty";
                } elseif (!$product->cin_id) {
                    $errors[] = "Internal Name '{$product->pr_detail_int}' does not exist in master";
                } else {
                    if ($product->pr_int_unit !== $product->cin_unitName) {
                        $errors[] = "Internal Unit mismatch. Product: '{$product->pr_int_unit}', Master: '{$product->cin_unitName}'";
                    }
                    if ($product->pr_int_unit_alt !== $product->cin_unitAltName) {
                        $errors[] = "Internal Unit Alt mismatch. Product: '{$product->pr_int_unit_alt}', Master: '{$product->cin_unitAltName}'";
                    }
                }
                
                $product->validation_status = (bool)$product->validation_status_raw;
                $product->validation_error = implode(' | ', $errors);
                return $product;
            });

        if (\Auth::user()->can('product_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (\Auth::user()->can('product_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }
        $this->resourceNeo['extraLinks'] = [];

        // Add import link to extraMainLinks
        $this->resourceNeo['extraMainLinks'] = [
            [
                'label' => 'Import',
                'link' => 'product.import',
                'icon' => 'M14,12L10,8V11H2V13H10V16M20,18V6C20,4.89 19.1,4 18,4H6A2,2 0 0,0 4,6V9H6V6H18V18H6V15H4V18A2,2 0 0,0 6,20H18A2,2 0 0,0 20,18Z'
            ]
        ];

        return Inertia::render('Admin/ProductIndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo, $formInfoMulti) {
            $table->withGlobalSearch();
            $arrKey = array_diff(array_keys($formInfo), ['subgroup','groupinfo']);
            $table->column('groupinfo_sname', 'Product Sub Group', searchable: false, sortable: true);
            $table->column('groupinfo_name', 'Product Group', searchable: false, sortable: true);
            foreach ($arrKey as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false, extra: ['type' => $formInfo[$key]['type'] ?? '', 'options' => [], 'align' => $formInfo[$key]['align'] ?? 'left']);
            }
            foreach (array_keys($formInfoMulti) as $key) {
                $table->column($key, $formInfoMulti[$key]['label'], searchable: $formInfoMulti[$key]['searchable'] ?? false, sortable: $formInfoMulti[$key]['sortable'] ?? false, hidden: $formInfoMulti[$key]['hidden'] ?? false, extra: ['align' => $formInfoMulti[$key]['align'] ?? 'left']);
            }
            
            

            $fresult = ['' => 'All'];
            $options1 = $formInfo['pr_pur_unit']['options'] ?? [];
            foreach ($options1 as $opt) {
                $opt && $fresult[$opt] = $opt;
            }
            $fresult2 = [];
            $options2 = $formInfo['groupinfo']['options'] ?? [];
            foreach ($options2 as $opt) {
                $opt && $fresult2[$opt['id']] = $opt['label'];
            }
            $fresult3 = [];
            foreach ([
  'Capex',
  'Consumable Item',
  'Indirect Expense/Purchase',
  'Opex',
  'Plant & Machinery Item',
  'Services Purchase',
  'Services Sale',
  'Stock Item',
  'Tools'
] as  $opt) {
                $opt && $fresult3[$opt] = $opt;
            }
            $table
                ->column('validation', 'Validation', sortable: true, extra: ['align' => 'center', 'width' => '100px'])
                ->column(label: 'Actions')
                ->selectFilter(key: 'pr_pur_unit', label: $formInfo['pr_pur_unit']['label'], options: $fresult, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'pr_pur_unit_alt', label: $formInfo['pr_pur_unit_alt']['label'], options: $fresult, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'pr_int_unit', label: $formInfo['pr_int_unit']['label'], options: $fresult, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'pr_int_unit_alt', label: $formInfo['pr_int_unit_alt']['label'], options: $fresult, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'groupinfo', label: 'Product Group', options: $fresult2, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'groupinfo_sname', label: 'Product Sub Group', options: $fresult3, noFilterOptionLabel: 'All');


            $table->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = Product::formInfo();
        return Inertia::render('Admin/ProductAddEditView', compact('resourceNeo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $formInfo = Product::formInfo();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }
        $savedArray['groupinfo'] = $request->groupinfo ? $request->groupinfo['id'] : null;
        $savedArray['pr_detail_int'] = $request->pr_detail_int ? $request->pr_detail_int['label'] : null;
        $request->validate($validateRule, [], $attributeNames);
        Product::create($savedArray);

        \ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[0]}]);

        return redirect()->route('product.index')->with(['message' => $this->resourceNeo['resourceTitle'] . ' Created Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $formdata = $product;
        $pgroup = Pgroup::find($product->groupinfo);
        $formdata->subgroup = $pgroup ? $pgroup->sgroup : null;
        $formdata->groupinfo = $pgroup ? ['id' => $product->groupinfo, 'label' => $pgroup->name, 'sgroup' => $pgroup->sgroup] : null;
        
        // Format pr_detail_int for dropdown
        if ($product->pr_detail_int) {
            $consumableName = \App\Models\ConsumableInternalName::where('name', $product->pr_detail_int)->first();
            if ($consumableName) {
                $formdata->pr_detail_int = [
                    'id' => $consumableName->id,
                    'label' => $consumableName->name,
                    'data' => [
                        'unitName' => $consumableName->unitName,
                        'unitAltName' => $consumableName->unitAltName
                    ]
                ];
            }
        }
        
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = Product::formInfo();
        return Inertia::render('Admin/ProductAddEditView', compact('formdata', 'resourceNeo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $formInfo = Product::formInfo();
        $attributeNames = [];
        $validateRule = [];
        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        $validateRule['pr_detail'] = 'required|unique:products,pr_detail,' . $product->id;
        $request->validate($validateRule, [], $attributeNames);
        foreach (array_diff(array_keys($formInfo), ['groupinfo', 'pr_detail_int', 'subgroup']) as $key) {
            $product->{$key} = $request->{$key};
        }
        $product->groupinfo = $request->groupinfo ? $request->groupinfo['id'] : null;
        $product->pr_detail_int = $request->pr_detail_int ? $request->pr_detail_int['label'] : null;
        $product->save();

        Purchase::where('pur_pr_id', $product->id)->update(['pur_pr_detail_int' => $product->pr_detail_int]);
        Outward::where('out_product_id', $product->id)->update(['out_product' => $product->pr_detail_int]);



        \ActivityLog::add(['action' => 'updated', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[0]}]);

        return redirect()->route('product.index')->with(['message' => 'Product Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $uname = $product->id;
        $product->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'product', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Product Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        Product::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : $uname = implode(',', request('ids'));
        \ActivityLog::add(['action' => 'deleted', 'module' => 'product', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected Product Deleted !!');
    }

    public function sync_name()
    {
        $products = Product::all();
        foreach ($products as $key => $product) {
            Purchase::where('pur_pr_id', $product->id)->update(['pur_pr_detail_int' => $product->pr_detail_int]);
            Outward::where('out_product_id', $product->id)->update(['out_product' => $product->pr_detail_int]);
        }
        return redirect()->route('dashboard')->with('message', 'Name Auto sync');
    }

    public function productOptions(Request $request)
    {
        return Product::getAllOption();
    }

    /**
     * Show the import view.
     */
    public function importView()
    {
        $this->resourceNeo['extraMainLinks'] = [
            [
                'link' => 'product.index',
                'label' => 'Back to List',
                'icon' => 'M18.6,6.62C17.16,6.62 15.8,7.18 14.83,8.15L7.8,14.39C7.16,15.03 6.31,15.38 5.4,15.38C3.53,15.38 2,13.87 2,12C2,10.13 3.53,8.62 5.4,8.62C6.31,8.62 7.16,8.97 7.84,9.65L8.97,10.65L10.5,9.31L9.22,8.2C8.2,7.18 6.84,6.62 5.4,6.62C2.42,6.62 0,9.04 0,12C0,14.96 2.42,17.38 5.4,17.38C6.84,17.38 8.2,16.82 9.17,15.85L16.2,9.61C16.84,8.97 17.69,8.62 18.6,8.62C20.47,8.62 22,10.13 22,12C22,13.87 20.47,15.38 18.6,15.38C17.7,15.38 16.84,15.03 16.16,14.35L15,13.34L13.5,14.68L14.78,15.8C15.8,16.81 17.15,17.37 18.6,17.37C21.58,17.37 24,14.96 24,12C24,9 21.58,6.62 18.6,6.62Z'
            ]
        ];

        $sampleData = [
           // ['subgroup' => 'Stock Item', 'groupinfo' => 'abc', 'pr_detail' => 'Product A', 'pr_hsn' => '12345678', 'pr_detail_int' => 'PROD-A', 'pr_pur_unit' => 'Kg', 'pr_int_unit' => 'Kg', 'pr_pur_unit_alt' => '', 'pr_int_unit_alt' => '', 'pr_min_unit' => '1', 'pr_gst_rate' => '18'],

            ['subgroup' => 'Stock Item', 'groupinfo' => 'Adhesives', 'pr_detail' => 'Product B', 'pr_hsn' => '87654321', 'pr_detail_int' => 'A Glue', 'pr_pur_unit' => 'Piece', 'pr_int_unit' => 'Pc.', 'pr_pur_unit_alt' => '', 'pr_int_unit_alt' => 'Pc.', 'pr_min_unit' => '1', 'pr_gst_rate' => '12'],
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
        $expectedHeaders = ['subgroup', 'groupinfo', 'pr_detail', 'pr_hsn', 'pr_detail_int', 'pr_pur_unit', 'pr_int_unit', 'pr_pur_unit_alt', 'pr_int_unit_alt', 'pr_min_unit', 'pr_gst_rate'];
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

        foreach ($records as $record) {
            // Skip empty rows
            if (empty(array_filter($record))) {
                $rowNumber++;
                continue;
            }

            $data = array_combine($headers, $record);

            // Create validator with same rules as store function
            $validator = \Validator::make($data, [
                'subgroup' => 'required',
                'groupinfo' => [
                    'required',
                    \Illuminate\Validation\Rule::exists('pgroups', 'name')->where(function ($query) use ($data) {
                        return $query->where('sgroup', $data['subgroup'] ?? '');
                    }),
                ],
                'pr_detail' => 'required|unique:products,pr_detail',
                'pr_hsn' => 'required|numeric',
                'pr_detail_int' => 'nullable|exists:consumable_internal_names,name',
                'pr_pur_unit' => 'required|exists:munits,name',
                'pr_int_unit' => [
                    'required',
                    'exists:munits,name',
                    function ($attribute, $value, $fail) use ($data) {
                        if (!empty($data['pr_detail_int'])) {
                            $cin = \App\Models\ConsumableInternalName::where('name', $data['pr_detail_int'])->first();
                            if ($cin && (string)$cin->unitName !== (string)$value) {
                                $fail("Internal Unit must be '{$cin->unitName}' as per Internal Name settings.");
                            }
                        }
                    }
                ],
                'pr_pur_unit_alt' => 'nullable|exists:munits,name',
                'pr_int_unit_alt' => [
                    'nullable',
                    'exists:munits,name',
                    function ($attribute, $value, $fail) use ($data) {
                        if (!empty($data['pr_detail_int'])) {
                            $cin = \App\Models\ConsumableInternalName::where('name', $data['pr_detail_int'])->first();
                            if ($cin && (string)$cin->unitAltName !== (string)$value) {
                                $fail("Internal Unit Alt must be '{$cin->unitAltName}' as per Internal Name settings.");
                            }
                        }
                    }
                ],
                'pr_min_unit' => 'required|numeric',
                'pr_gst_rate' => 'required|numeric',
            ], [
                'subgroup.required' => 'Sub Group is required',
                'groupinfo.required' => 'Product Group is required',
                'groupinfo.exists' => 'Invalid Product Group and Sub Group combination or Product Group does not exist',
                'pr_detail.required' => 'Product Name is required',
                'pr_detail.unique' => 'Product Name already exists',
                'pr_detail_int.exists' => 'Internal Name does not exist in Consumable Internal Name Master',
                'pr_hsn.required' => 'HSN Code is required',
                'pr_hsn.numeric' => 'HSN Code must be numeric',
                'pr_pur_unit.required' => 'Billed Unit is required',
                'pr_pur_unit.exists' => 'Billed Unit does not exist in Master Unit table',
                'pr_int_unit.required' => 'Internal Unit is required',
                'pr_int_unit.exists' => 'Internal Unit does not exist in Master Unit table',
                'pr_pur_unit_alt.exists' => 'Billed Unit Alt does not exist in Master Unit table',
                'pr_int_unit_alt.exists' => 'Internal Unit Alt does not exist in Master Unit table',
                'pr_min_unit.required' => 'Conversion Value is required',
                'pr_min_unit.numeric' => 'Conversion Value must be numeric',
                'pr_gst_rate.required' => 'GST Rate is required',
                'pr_gst_rate.numeric' => 'GST Rate must be numeric',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
            } else {
                // Get validated data
                $validatedRecord = $validator->validated();
                
                // Convert groupinfo from name to ID
                $pgroup = Pgroup::where('name', $validatedRecord['groupinfo'])
                                ->where('sgroup', $validatedRecord['subgroup'])
                                ->first();
                if ($pgroup) {
                    $validatedRecord['groupinfo'] = $pgroup->id;
                }
                
                unset($validatedRecord['subgroup']);
                $validatedData[] = $validatedRecord;
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
                Product::create($data);
            }

            \DB::commit();
            \ActivityLog::add(['action' => 'imported', 'module' => 'product', 'data_key' => count($validatedData)]);

            return redirect()->route('product.index')
                ->with([
                    'message' => count($validatedData) . ' product records imported successfully!',
                    'msg_type' => 'success'
                ]);
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Product import failed: ' . $e->getMessage());
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! An unexpected error occurred while importing the data. Please try again.',
                    'msg_type' => 'danger'
                ]);
        }
    }
}
