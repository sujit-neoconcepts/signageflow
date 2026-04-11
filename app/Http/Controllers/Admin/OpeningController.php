<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use App\Helpers\ActivityLog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AverageUnitPriceService;


class OpeningController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'opening', 'resourceTitle' => 'Opening Stock', 'iconPath' => 'M11 9H13V6H16V4H13V1H11V4H8V6H11M7 18C5.9 18 5 18.9 5 20S5.9 22 7 22 9 21.1 9 20 8.1 18 7 18M17 18C15.9 18 15 18.9 15 20S15.9 22 17 22 19 21.1 19 20 18.1 18 17 18M7.2 14.8V14.7L8.1 13H15.5C16.2 13 16.9 12.6 17.2 12L21.1 5L19.4 4L15.5 11H8.5L4.3 2H1V4H3L6.6 11.6L5.2 14C5.1 14.3 5 14.6 5 15C5 16.1 5.9 17 7 17H19V15H7.4C7.3 15 7.2 14.9 7.2 14.8Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:opening_list', ['only' => ['index', 'show']]);
        $this->middleware('can:opening_create', ['only' => ['create', 'store', 'importView', 'import']]);
        $this->middleware('can:opening_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:opening_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:opening_delete', ['only' => ['destroy']]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo = Purchase::formInfo();
        $formInfo['pur_inv'] = ['label' => 'Comment', 'searchable' => true, 'sortable' => true, 'vRule' => 'required', 'default' => 'Opening-' . date('d-m-Y H:i:s')];
        unset($formInfo['pur_supplier']);
        $formInfoMulti = Purchase::formInfoMulti();

        unset($formInfoMulti['pur_pr_detail']);
        unset($formInfo['received_date']);
        unset($formInfoMulti['pur_pr_hsn']);
        unset($formInfoMulti['pur_qty']);
        unset($formInfoMulti['pur_qty_alt']);
        unset($formInfoMulti['pur_unit']);
        unset($formInfoMulti['pur_unit_alt']);
        unset($formInfoMulti['pur_unit_conv_rate']);
        unset($formInfoMulti['pur_rate']);
        //unset($formInfoMulti['pur_rate_int']);
        //unset($formInfoMulti['pur_amnt']);
        unset($formInfoMulti['pur_gst_amnt']);
        unset($formInfoMulti['pur_amnt_total']);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    foreach (array_keys($formInfo) as $key) {
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                    foreach (array_keys($formInfoMulti) as $key) {
                        if (in_array($key, ['last_rate', 'unit_rate', 'available_qty'])) continue;
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $filter_array = [];
        foreach (array_merge(array_diff(array_keys($formInfo), []), array_keys($formInfoMulti)) as $fvalue) {
            $filter_array[] = AllowedFilter::exact($fvalue);
        }
        $query = Purchase::select('purchases.*', 'pgroups.name as groupinfo_name')->where('entry_type', 1)->leftJoin('products', 'products.id', 'purchases.pur_pr_id', 'left')->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo', 'left');

        //$query->inFinancialYear();

        if (\Auth::user()->can('all') || \Auth::user()->can('opening_list_for_all')) {
        } else {
            $query = $query->where('pur_incharge', \Auth::user()->name);
        }

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('-pur_date')
            ->allowedSorts(array_merge(array_diff(array_keys($formInfo), []), array_keys($formInfoMulti), []))
            ->allowedFilters(array_merge($filter_array, [AllowedFilter::scope('pur_date_start'), AllowedFilter::scope('pur_date_end'), $globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (\Auth::user()->can('opening_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (\Auth::user()->can('opening_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }
        $this->resourceNeo['extraLinks'] = [];

        $this->resourceNeo['extraLinks'] = [
            [
                'label' => 'Generate Barcode',
                'link' => 'purchase.barcode',
                'icon' => 'M2,6H4V18H2V6M5,6H6V18H5V6M7,6H10V18H7V6M11,6H12V18H11V6M14,6H16V18H14V6M17,6H20V18H17V6M21,6H22V18H21V6Z',
                'key' => 'id',
                'cond' => '*',
                'compvl' => '*'
            ]
        ];

        // Add import link to extraMainLinks
        $this->resourceNeo['extraMainLinks'] = [
            [
                'label' => 'Import',
                'link' => 'opening.import',
                'icon' => 'M14,12L10,8V11H2V13H10V16M20,18V6C20,4.89 19.1,4 18,4H6A2,2 0 0,0 4,6V9H6V6H18V18H6V15H4V18A2,2 0 0,0 6,20H18A2,2 0 0,0 20,18Z'
            ]
        ];
        $this->resourceNeo['showTotal'] = true;
        $this->resourceNeo['showall'] = true;


        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo, $formInfoMulti) {
            $table->withGlobalSearch();
            $arrKey = array_diff(array_keys($formInfo), []);
            foreach ($arrKey as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false, extra: ['type' => $formInfo[$key]['type'] ?? '', 'options' => [], 'align' => $formInfo[$key]['align'] ?? 'left', 'showTotal' => $formInfo[$key]['showTotal'] ?? false]);
            }
            foreach (array_keys($formInfoMulti) as $key) {
                if (in_array($key, ['last_rate', 'unit_rate', 'available_qty'])) continue;
                $table->column($key, $formInfoMulti[$key]['label'], searchable: $formInfoMulti[$key]['searchable'] ?? false, sortable: $formInfoMulti[$key]['sortable'] ?? false, hidden: $formInfoMulti[$key]['hidden'] ?? false, extra: ['align' => $formInfoMulti[$key]['align'] ?? 'left', 'showTotal' => $formInfoMulti[$key]['showTotal'] ?? false]);
            }

            $fresult2 = [];
            foreach ($formInfoMulti['pur_incharge']['options'] as  $opt) {
                $opt && $fresult2[$opt] = $opt;
            }
            $fresult3 = [];
            foreach ($formInfoMulti['pur_unint_int']['options'] as  $opt) {
                $opt && $fresult3[$opt] = $opt;
            }
            $fresult4 = [];
            foreach ($formInfoMulti['pur_loc']['options'] as  $opt) {
                $opt && $fresult4[$opt] = $opt;
            }
            $table
                ->column(label: 'Prod Group', key: 'groupinfo_name')
                ->column(label: 'Actions')
                ->dateFilter(key: 'pur_date_start', label: 'Date From')
                ->dateFilter(key: 'pur_date_end', label: 'Date To');

            if (\Auth::user()->can('all') || \Auth::user()->can('opening_list_for_all')) {
                $table->selectFilter(key: 'pur_incharge', label: $formInfoMulti['pur_incharge']['label'], options: $fresult2, noFilterOptionLabel: 'All');
            }

            $table->selectFilter(key: 'pur_unint_int', label: $formInfoMulti['pur_unint_int']['label'], options: $fresult3, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'pur_unint_int_alt', label: $formInfoMulti['pur_unint_int_alt']['label'], options: $fresult3, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'pur_loc', label: $formInfoMulti['pur_loc']['label'], options: $fresult4, noFilterOptionLabel: 'All')

                ->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['Multilabel'] = 'Line items';
        $resourceNeo['fColumn'] = 3;
        $resourceNeo['fColumnMulti'] = 8;
        $resourceNeo['AllowMore'] = true;
        $resourceNeo['AllowDel'] = true;
        $resourceNeo['formInfo'] = Purchase::formInfo();
        unset($resourceNeo['formInfo']['received_date']);
        $resourceNeo['formInfo']['pur_inv'] = ['label' => 'Comment', 'searchable' => true, 'sortable' => true, 'vRule' => 'required', 'default' => 'Opening-' . date('d-m-Y H:i:s')];
        unset($resourceNeo['formInfo']['pur_supplier']);

        $resourceNeo['formInfoMulti'] = Purchase::formInfoMulti();

        $resourceNeo['formInfoMulti']['pur_pr_detail_int'] = ['label' => 'Internal name', 'searchable' => true, 'sortable' => true, 'type' => 'select', 'options' => Product::getAllOptionInternal(), 'vRule' => 'required', 'colspan' => 3];
        $resourceNeo['formInfoMulti']['pur_qty_int']['readonly'] = false;

        unset($resourceNeo['formInfoMulti']['pur_pr_detail']);
        unset($resourceNeo['formInfoMulti']['pur_pr_hsn']);
        unset($resourceNeo['formInfoMulti']['pur_qty']);
        unset($resourceNeo['formInfoMulti']['pur_qty_alt']);
        unset($resourceNeo['formInfoMulti']['pur_unit']);
        unset($resourceNeo['formInfoMulti']['pur_unit_alt']);
        unset($resourceNeo['formInfoMulti']['pur_unit_conv_rate']);
        unset($resourceNeo['formInfoMulti']['pur_rate']);
        //unset($resourceNeo['formInfoMulti']['pur_rate_int']);
        //unset($resourceNeo['formInfoMulti']['pur_amnt']);
        unset($resourceNeo['formInfoMulti']['pur_gst_amnt']);
        unset($resourceNeo['formInfoMulti']['pur_amnt_total']);
        unset($resourceNeo['formInfoMulti']['last_rate']);
        unset($resourceNeo['formInfoMulti']['unit_rate']);
        unset($resourceNeo['formInfoMulti']['available_qty']);

        return Inertia::render('Admin/OpeningAddEditView', compact('resourceNeo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $formInfo = Purchase::formInfo();
        $resourceNeo['formInfo']['pur_inv']['vRule'] = 'required';

        $formInfoMulti = Purchase::formInfoMulti();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];
        foreach (array_diff(array_keys($formInfo), ['pur_supplier']) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }
        foreach (array_diff(array_keys($formInfoMulti), ['pur_pr_detail', 'pur_pr_hsn', 'pur_qty', 'pur_qty_alt', 'pur_unit', 'pur_unit_alt', 'pur_unit_conv_rate', 'pur_rate', 'pur_gst_amnt', 'pur_amnt_total', 'last_rate', 'unit_rate', 'available_qty']) as $key) {
            $attributeNames['multi.*.' . $key] = $formInfoMulti[$key]['label'];
            isset($formInfoMulti[$key]['vRule']) && $validateRule['multi.*.' . $key] = $formInfoMulti[$key]['vRule'];
        }

        $request->validate($validateRule, [], $attributeNames);
        $savedArray['pur_date'] = $savedArray['received_date']= date('Y-m-d', strtotime($request->pur_date));


        foreach ($request->multi as $ml) {
            foreach (array_diff(array_keys($formInfoMulti), ['pur_pr_detail', 'pur_pr_hsn', 'pur_qty', 'pur_qty_alt', 'pur_unit', 'pur_unit_alt', 'pur_unit_conv_rate', 'pur_rate', 'pur_gst_amnt', 'pur_amnt_total', 'last_rate', 'unit_rate', 'available_qty']) as $key) {
                $savedArray[$key] = $ml[$key];
            }
            $savedArray['pur_pr_detail_int'] = $ml['pur_pr_detail_int']['label'];
            $savedArray['pur_pr_id'] = $ml['pur_pr_detail_int']['id'];
            $savedArray['entry_type'] = 1;

            // Update average unit price BEFORE saving to avoid double-counting
            if (!empty($savedArray['pur_pr_detail_int']) && !empty($savedArray['pur_qty_int']) && !empty($savedArray['pur_rate_int'])) {
                $averagePriceService = new AverageUnitPriceService();
                $averagePriceService->calculateAndUpdateAveragePrice(
                    $savedArray['pur_pr_detail_int'],
                    $savedArray['pur_qty_int'],
                    $savedArray['pur_rate_int']
                );
            }

            Purchase::create($savedArray);
        }

        \ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[1]}]);

        return redirect()->route('opening.index')->with(['message' => $this->resourceNeo['resourceTitle'] . ' Created Successfully !!', 'msg_type' => 'info']);
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit($purchase_id)
    {
        $purchase = Purchase::find($purchase_id);
        $formdata = $purchase;
        $temp = [];
        $formInfoMulti = Purchase::formInfoMulti();
        $formInfoMulti['pur_qty_int']['readonly'] = false;
        unset($formInfoMulti['pur_pr_detail']);
        unset($formInfoMulti['pur_pr_hsn']);
        unset($formInfoMulti['pur_qty']);
        unset($formInfoMulti['pur_qty_alt']);
        unset($formInfoMulti['pur_unit']);
        unset($formInfoMulti['pur_unit_alt']);
        unset($formInfoMulti['pur_unit_conv_rate']);
        unset($formInfoMulti['pur_rate']);
        //unset($formInfoMulti['pur_rate_int']);
        //unset($formInfoMulti['pur_amnt']);
        unset($formInfoMulti['pur_gst_amnt']);
        unset($formInfoMulti['pur_amnt_total']);
        unset($formInfoMulti['last_rate']);
        unset($formInfoMulti['unit_rate']);
        unset($formInfoMulti['available_qty']);
        
        foreach (array_keys($formInfoMulti) as $key) {
            $temp[$key] = $purchase->{$key};
        }
        $formdata->multi = [$temp];
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['Multilabel'] = 'Line items';
        $resourceNeo['fColumn'] = 3;
        $resourceNeo['fColumnMulti'] = 8;
        $resourceNeo['AllowMore'] = false;
        $resourceNeo['AllowDel'] = false;
        $resourceNeo['formInfo'] = Purchase::formInfo();
        $resourceNeo['formInfoMulti'] = $formInfoMulti;
        $resourceNeo['formInfo']['pur_inv'] = ['label' => 'Comment', 'searchable' => true, 'sortable' => true, 'vRule' => 'required', 'default' => 'Opening-' . date('d-m-Y H:i:s')];
        unset($resourceNeo['formInfo']['pur_supplier']);
        $resourceNeo['formInfoMulti']['pur_pr_detail_int']['colspan'] = 3;

        return Inertia::render('Admin/OpeningAddEditView', compact('formdata', 'resourceNeo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $purchase_id)
    {
        $purchase = Purchase::find($purchase_id);
        $formInfo = Purchase::formInfo();
        $formInfoMulti = Purchase::formInfoMulti();
        $attributeNames = [];
        $validateRule = [];
        foreach (array_diff(array_keys($formInfo), ['pur_inv', 'pur_supplier']) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        foreach (array_diff(array_keys($formInfoMulti), ['pur_pr_detail', 'pur_pr_hsn', 'pur_qty', 'pur_qty_alt', 'pur_unit', 'pur_unit_alt', 'pur_unit_conv_rate', 'pur_rate',  'pur_gst_amnt', 'pur_amnt_total', 'last_rate', 'unit_rate', 'available_qty']) as $key) {
            $attributeNames['multi.*.' . $key] = $formInfoMulti[$key]['label'];
            isset($formInfoMulti[$key]['vRule']) && $validateRule['multi.*.' . $key] = $formInfoMulti[$key]['vRule'];
        }
        $request->validate($validateRule, [], $attributeNames);
        foreach (array_diff(array_keys($formInfo), ['pur_supplier']) as $key) {
            $purchase->{$key} = $request->{$key};
        }
        $purchase->pur_date = $purchase->received_date = date('Y-m-d', strtotime($request->pur_date));
        foreach ($request->multi as $ml) {
            foreach (array_diff(array_keys($formInfoMulti), ['pur_pr_detail', 'pur_pr_hsn', 'pur_qty', 'pur_qty_alt', 'pur_unit', 'pur_unit_alt', 'pur_unit_conv_rate', 'pur_rate',  'pur_gst_amnt', 'pur_amnt_total', 'last_rate', 'unit_rate', 'available_qty']) as $key) {
                $purchase->{$key} = $ml[$key];
            }
        }


        // Update average unit price BEFORE saving (service needs OLD values from database)
        if (!empty($purchase->pur_pr_detail_int) && !empty($purchase->pur_qty_int) && !empty($purchase->pur_rate_int)) {
            $averagePriceService = new AverageUnitPriceService();
            $averagePriceService->calculateAndUpdateAveragePrice(
                $purchase->pur_pr_detail_int,
                $purchase->pur_qty_int,
                $purchase->pur_rate_int,
                $purchase->id  // Service will fetch OLD values from DB before they're overwritten
            );
        }

        $purchase->save();

        \ActivityLog::add(['action' => 'updated', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfo)[1]}]);

        return redirect()->route('opening.index')->with(['message' => 'Purchase Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($purchase_id)
    {
        $purchase = Purchase::find($purchase_id);
        $uname = $purchase->id;
        $purchase->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'purchase', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Purchase Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        Purchase::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : $uname = implode(',', request('ids'));
        \ActivityLog::add(['action' => 'deleted', 'module' => 'purchase', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected Purchase Deleted !!');
    }

    /**
     * Show the import view.
     */
    public function importView()
    {
        $this->resourceNeo['extraMainLinks'] = [
            [
                'link' => 'opening.index',
                'label' => 'Back to List',
                'icon' => 'M11 9H13V6H16V4H13V1H11V4H8V6H11M7 18C5.9 18 5 18.9 5 20S5.9 22 7 22 9 21.1 9 20 8.1 18 7 18M17 18C15.9 18 15 18.9 15 20S15.9 22 17 22 19 21.1 19 20 18.1 18 17 18M7.2 14.8V14.7L8.1 13H15.5C16.2 13 16.9 12.6 17.2 12L21.1 5L19.4 4L15.5 11H8.5L4.3 2H1V4H3L6.6 11.6L5.2 14C5.1 14.3 5 14.6 5 15C5 16.1 5.9 17 7 17H19V15H7.4C7.3 15 7.2 14.9 7.2 14.8Z'
            ]
        ];

        $sampleData = [
            [
                'pur_date' => date('Y-m-d'),
                'pur_inv' => 'Opening-001',
                'pur_incharge' => 'supervisor User',
                'pur_loc' => 'Unit - 1',
                'pur_pr_detail_int' => 'BOPP Tape (Black)',
                'pur_qty_int' => '100',
                'pur_unint_int' => 'Rolls',
                'pur_qty_int_alt' => '10',
                'pur_unint_int_alt' => 'Rolls',
                'pur_rate_int' => '50',
                'remark' => 'Initial Stock'
            ],
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
        $expectedHeaders = ['pur_date', 'pur_inv', 'pur_incharge', 'pur_loc', 'pur_pr_detail_int', 'pur_qty_int', 'pur_unint_int', 'pur_qty_int_alt', 'pur_unint_int_alt', 'pur_rate_int', 'remark'];
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

            // Set default values if empty
            if (empty($data['pur_date'])) {
                $data['pur_date'] = date('Y-m-d');
            }
            if (empty($data['pur_inv'])) {
                $data['pur_inv'] = 'OPNINV-' . date('YmdHis') . '-' . $rowNumber;
            }

            // Create validator with same rules as store function
            $validator = \Validator::make($data, [
                'pur_date' => 'required|date',
                'pur_inv' => 'required',
                'pur_incharge' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $user = User::where('name', $value)->first();
                        if (!$user || !$user->hasRole('supervisor')) {
                            $fail('The ' . $attribute . ' must be a valid supervisor.');
                        }
                    },
                ],
                'pur_loc' => 'required|exists:locations,name',
                'pur_pr_detail_int' => 'required|exists:products,pr_detail_int',
                'pur_qty_int' => 'required|numeric',
                'pur_unint_int' => 'required',
                'pur_qty_int_alt' => 'nullable|numeric',
                'pur_unint_int_alt' => 'nullable',
                'pur_rate_int' => 'required|numeric',
                'remark' => 'nullable',
            ], [
                'pur_date.required' => 'Date is required',
                'pur_date.date' => 'Invalid date format',
                'pur_inv.required' => 'Invoice/Comment is required',
                'pur_incharge.required' => 'Incharge is required',
                'pur_loc.required' => 'Location is required',
                'pur_loc.exists' => 'Location does not exist',
                'pur_pr_detail_int.required' => 'Internal Name(pur_pr_detail_int) is required',
                'pur_pr_detail_int.exists' => 'Internal Name does not exist in Product Master',
                'pur_qty_int.required' => 'Quantity is required',
                'pur_qty_int.numeric' => 'Quantity must be numeric',
                'pur_unint_int.required' => 'Unit is required',
                'pur_rate_int.required' => 'Rate is required',
                'pur_rate_int.numeric' => 'Rate must be numeric',
            ]);

            // Add custom validation for units matching the product
            $validator->after(function ($validator) use ($data) {
                if (!empty($data['pur_pr_detail_int'])) {
                    $product = Product::where('pr_detail_int', $data['pur_pr_detail_int'])->first();
                    if ($product) {
                        if (isset($data['pur_unint_int']) && $data['pur_unint_int'] !== $product->pr_int_unit) {
                            $validator->errors()->add('pur_unint_int', "As per Product Master Internal Unit must be '{$product->pr_int_unit}' for this product.");
                        }
                        if (isset($data['pur_unint_int_alt']) && !empty($data['pur_unint_int_alt']) && $data['pur_unint_int_alt'] !== $product->pr_int_unit_alt) {
                            $validator->errors()->add('pur_unint_int_alt', "As per Product Master Internal Unit Alt must be '{$product->pr_int_unit_alt}' for this product.");
                        }
                    }
                }
            });

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
            } else {
                // Get validated data
                $validatedRecord = $validator->validated();
                
                // Get Product ID
                $product = Product::where('pr_detail_int', $validatedRecord['pur_pr_detail_int'])->first();
                if ($product) {
                    $validatedRecord['pur_pr_id'] = $product->id;
                    
                    // Calculate amount
                    $validatedRecord['pur_amnt'] = $validatedRecord['pur_qty_int'] * $validatedRecord['pur_rate_int'];
                    
                    // Set entry type for opening stock
                    $validatedRecord['entry_type'] = 1;
                    
                    // Format date
                    $validatedRecord['pur_date'] = $validatedRecord['received_date'] = date('Y-m-d', strtotime($validatedRecord['pur_date']));
                }
                
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
                // Update average unit price BEFORE saving to avoid double-counting
                if (!empty($data['pur_pr_detail_int']) && !empty($data['pur_qty_int']) && !empty($data['pur_rate_int'])) {
                    $averagePriceService = new AverageUnitPriceService();
                    $averagePriceService->calculateAndUpdateAveragePrice(
                        $data['pur_pr_detail_int'],
                        $data['pur_qty_int'],
                        $data['pur_rate_int']
                    );
                }

                Purchase::create($data);
            }

            \DB::commit();
            \ActivityLog::add(['action' => 'imported', 'module' => 'opening', 'data_key' => count($validatedData)]);

            return redirect()->route('opening.index')
                ->with([
                    'message' => count($validatedData) . ' opening stock records imported successfully!',
                    'msg_type' => 'success'
                ]);
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Opening stock import failed: ' . $e->getMessage());
            return redirect()->back()
                ->with([
                    'message' => 'Import failed! An unexpected error occurred while importing the data. Please try again.',
                    'msg_type' => 'danger'
                ]);
        }
    }
}
