<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseInfo;
use App\Services\AverageUnitPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PurchaseController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'purchase', 'resourceTitle' => 'Purchase Master', 'iconPath' => 'M18.6,6.62C17.16,6.62 15.8,7.18 14.83,8.15L7.8,14.39C7.16,15.03 6.31,15.38 5.4,15.38C3.53,15.38 2,13.87 2,12C2,10.13 3.53,8.62 5.4,8.62C6.31,8.62 7.16,8.97 7.84,9.65L8.97,10.65L10.5,9.31L9.22,8.2C8.2,7.18 6.84,6.62 5.4,6.62C2.42,6.62 0,9.04 0,12C0,14.96 2.42,17.38 5.4,17.38C6.84,17.38 8.2,16.82 9.17,15.85L16.2,9.61C16.84,8.97 17.69,8.62 18.6,8.62C20.47,8.62 22,10.13 22,12C22,13.87 20.47,15.38 18.6,15.38C17.7,15.38 16.84,15.03 16.16,14.35L15,13.34L13.5,14.68L14.78,15.8C15.8,16.81 17.15,17.37 18.6,17.37C21.58,17.37 24,14.96 24,12C24,9 21.58,6.62 18.6,6.62Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:purchase_list', ['only' => ['index', 'itemwiseIndex', 'show']]);
        $this->middleware('can:purchase_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:purchase_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:purchase_delete', ['only' => ['destroy']]);
    }

    /**
     * Display grouped invoice listing for purchases.
     */
    public function index()
    {
        $formInfo = [
            'pur_inv' => ['label' => 'Invoice No', 'searchable' => true, 'sortable' => true],
            'pur_date' => ['label' => 'Purchase Date', 'sortable' => true, 'type' => 'datepicker'],
            'received_date' => ['label' => 'Received Date', 'sortable' => true, 'type' => 'datepicker'],
            'pur_supplier' => ['label' => 'Supplier Name', 'searchable' => true, 'sortable' => true, 'type' => 'select', 'optionType' => 'array', 'options' => Purchase::formInfo()['pur_supplier']['options']],
            'line_count' => ['label' => 'Item Count', 'sortable' => true, 'align' => 'right'],
            'sum_total' => ['label' => 'Sum Total', 'sortable' => true, 'align' => 'right', 'showTotal' => true],
        ];

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo) {
            $query->where(function ($query) use ($value, $formInfo) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo) {
                    foreach (array_keys($formInfo) as $key) {
                        if ($key === 'line_count' || $key === 'sum_total') {
                            continue;
                        }
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;
        $filterArray = [];
        foreach (array_keys($formInfo) as $fvalue) {
            if ($fvalue === 'line_count') {
                continue;
            }
            $filterArray[] = AllowedFilter::exact($fvalue);
        }

        $lineAggQuery = Purchase::query()
            ->select([
                'purchase_info_id',
                DB::raw('COUNT(*) as line_count'),
                DB::raw('MIN(id) as id'),
            ])
            ->where('entry_type', 0)
            ->groupBy('purchase_info_id');

        if (!(Auth::user()->can('all') || Auth::user()->can('purchase_list_for_all'))) {
            $lineAggQuery->where('pur_incharge', Auth::user()->name);
        }

        $query = PurchaseInfo::query()
            ->select([
                'purchases_info.pur_inv',
                'purchases_info.pur_date',
                'purchases_info.received_date',
                'purchases_info.pur_supplier',
                'purchases_info.sum_total',
                'line_agg.line_count',
                'line_agg.id',
            ])
            ->joinSub($lineAggQuery, 'line_agg', function ($join) {
                $join->on('line_agg.purchase_info_id', '=', 'purchases_info.id');
            });

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('-pur_date')
            ->allowedSorts(array_keys($formInfo))
            ->allowedFilters(array_merge($filterArray, [AllowedFilter::scope('pur_date_start'), AllowedFilter::scope('pur_date_end'), AllowedFilter::scope('received_date_start'), AllowedFilter::scope('received_date_end'), $globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (Auth::user()->can('purchase_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (Auth::user()->can('purchase_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        $this->resourceNeo['extraMainLinks'] = [
            [
                'label' => 'Itemwise List',
                'link' => 'purchase.itemwise',
                'icon' => 'M3,13H11V11H3M3,6V8H21V6M3,18H11V16H3V18M13,18H21V16H13M13,13H21V11H13V13Z',
            ],
        ];
        $this->resourceNeo['detailModal'] = true;
        $this->resourceNeo['detailModalRoute'] = 'purchase.detailView';
        $this->resourceNeo['detailModalTitle'] = 'Purchase Details';

        $this->resourceNeo['showTotal'] = true;
        $this->resourceNeo['showall'] = true;

        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo) {
            $table->withGlobalSearch();

            foreach (array_keys($formInfo) as $key) {
                $table->column(
                    $key,
                    $formInfo[$key]['label'],
                    searchable: $formInfo[$key]['searchable'] ?? false,
                    sortable: $formInfo[$key]['sortable'] ?? false,
                    hidden: $formInfo[$key]['hidden'] ?? false,
                    extra: ['type' => $formInfo[$key]['type'] ?? '', 'options' => [], 'align' => $formInfo[$key]['align'] ?? 'left', 'showTotal' => $formInfo[$key]['showTotal'] ?? false]
                );
            }

            $fresult = [];
            foreach ($formInfo['pur_supplier']['options'] as $opt) {
                $opt && $fresult[$opt] = $opt;
            }

            $table
                ->column(label: 'Actions')
                ->dateFilter(key: 'pur_date_start', label: 'Pur. Date From')
                ->dateFilter(key: 'pur_date_end', label: 'Pur. Date To')
                ->dateFilter(key: 'received_date_start', label: 'Received Date From')
                ->dateFilter(key: 'received_date_end', label: 'Received Date To')
                ->selectFilter(key: 'pur_supplier', label: $formInfo['pur_supplier']['label'], options: $fresult, noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }

    /**
     * Display the legacy row-wise listing for purchases.
     */
    public function itemwiseIndex()
    {
        $formInfo = Purchase::formInfo();
        $formInfoMulti = Purchase::formInfoMulti();

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    foreach (array_keys($formInfo) as $key) {
                        if ($key === 'roundoff') {
                            continue;
                        }
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                    foreach (array_keys($formInfoMulti) as $key) {
                        if (in_array($key, ['last_rate', 'unit_rate', 'available_qty'])) {
                            continue;
                        }
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;
        $filterArray = [];
        foreach (array_merge(array_diff(array_keys($formInfo), ['roundoff']), array_keys($formInfoMulti)) as $fvalue) {
            $filterArray[] = AllowedFilter::exact($fvalue);
        }

        $query = Purchase::select('purchases.*', 'pgroups.name as groupinfo_name', 'pgroups.sgroup as groupinfo_sname')
            ->where('entry_type', 0)
            ->leftJoin('products', 'products.id', 'purchases.pur_pr_id', 'left')
            ->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo', 'left');

        if (!(Auth::user()->can('all') || Auth::user()->can('purchase_list_for_all'))) {
            $query = $query->where('pur_incharge', Auth::user()->name);
        }

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('-pur_date')
            ->allowedSorts(array_merge(array_diff(array_keys($formInfo), ['roundoff']), array_keys($formInfoMulti), []))
            ->allowedFilters(array_merge($filterArray, [AllowedFilter::scope('pur_date_start'), AllowedFilter::scope('pur_date_end'), AllowedFilter::scope('received_date_start'), AllowedFilter::scope('received_date_end'), AllowedFilter::exact('groupinfo_sname', 'pgroups.sgroup'), $globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (Auth::user()->can('purchase_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (Auth::user()->can('purchase_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }
        $this->resourceNeo['actions'] = ['r'];

        $this->resourceNeo['extraMainLinks'] = [
            [
                'label' => 'Grouped List',
                'link' => 'purchase.index',
                'icon' => 'M3,13H11V11H3M3,6V8H21V6M3,18H11V16H3V18M13,18H21V16H13M13,13H21V11H13V13Z',
            ],
        ];

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

        $this->resourceNeo['showTotal'] = true;
        $this->resourceNeo['showall'] = true;

        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo, $formInfoMulti) {
            $table->withGlobalSearch();

            $arrKey = array_diff(array_keys($formInfo), ['roundoff']);
            foreach ($arrKey as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false, extra: ['type' => $formInfo[$key]['type'] ?? '', 'options' => [], 'align' => $formInfo[$key]['align'] ?? 'left', 'showTotal' => $formInfo[$key]['showTotal'] ?? false]);
            }

            foreach (array_keys($formInfoMulti) as $key) {
                if (in_array($key, ['last_rate', 'unit_rate', 'available_qty'])) {
                    continue;
                }
                $table->column($key, $formInfoMulti[$key]['label'], searchable: $formInfoMulti[$key]['searchable'] ?? false, sortable: $formInfoMulti[$key]['sortable'] ?? false, hidden: $formInfoMulti[$key]['hidden'] ?? false, extra: ['align' => $formInfoMulti[$key]['align'] ?? 'left', 'showTotal' => $formInfoMulti[$key]['showTotal'] ?? false]);
            }

            $fresult = [];
            foreach ($formInfo['pur_supplier']['options'] as $opt) {
                $opt && $fresult[$opt] = $opt;
            }

            $fresult2 = [];
            foreach ($formInfoMulti['pur_incharge']['options'] as $opt) {
                $opt && $fresult2[$opt] = $opt;
            }

            $fresult3 = [];
            foreach ($formInfoMulti['pur_unit']['options'] as $opt) {
                $opt && $fresult3[$opt] = $opt;
            }

            $fresult4 = [];
            foreach ($formInfoMulti['pur_loc']['options'] as $opt) {
                $opt && $fresult4[$opt] = $opt;
            }

            $fresult5 = [];
            foreach (['Capex', 'Consumable Item', 'Indirect Expense/Purchase', 'Opex', 'Plant & Machinery Item', 'Services Purchase', 'Services Sale', 'Stock Item', 'Tools'] as $opt) {
                $opt && $fresult5[$opt] = $opt;
            }

            $table
                ->column(label: 'Prod Group', key: 'groupinfo_name')
                ->column(label: 'Used Type', key: 'groupinfo_sname')
                ->column(label: 'Actions')
                ->dateFilter(key: 'pur_date_start', label: 'Pur. Date From')
                ->dateFilter(key: 'pur_date_end', label: 'Pur. Date To')
                ->dateFilter(key: 'received_date_start', label: 'Received Date From')
                ->dateFilter(key: 'received_date_end', label: 'ReceivedDate To')
                ->selectFilter(key: 'pur_supplier', label: $formInfo['pur_supplier']['label'], options: $fresult, noFilterOptionLabel: 'All');

            if (Auth::user()->can('all') || Auth::user()->can('purchase_list_for_all')) {
                $table->selectFilter(key: 'pur_incharge', label: $formInfoMulti['pur_incharge']['label'], options: $fresult2, noFilterOptionLabel: 'All');
            }

            $table->selectFilter(key: 'pur_unit', label: $formInfoMulti['pur_unit']['label'], options: $fresult3, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'pur_unit_alt', label: $formInfoMulti['pur_unit_alt']['label'], options: $fresult3, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'pur_unint_int', label: $formInfoMulti['pur_unint_int']['label'], options: $fresult3, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'pur_unint_int_alt', label: $formInfoMulti['pur_unint_int_alt']['label'], options: $fresult3, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'pur_loc', label: $formInfoMulti['pur_loc']['label'], options: $fresult4, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'groupinfo_sname', label: 'Used Type', options: $fresult5, noFilterOptionLabel: 'All')
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
        $resourceNeo['fColumn'] = 5;
        $resourceNeo['fColumnMulti'] = 8;
        $resourceNeo['AllowMore'] = true;
        $resourceNeo['AllowDel'] = true;
        $resourceNeo['formInfo'] = Purchase::formInfo();
        $resourceNeo['formInfoMulti'] = Purchase::formInfoMulti();
        
        $resourceNeo['productSubgroups'] = [
            'Capex', 'Consumable Item', 'Indirect Expense/Purchase', 'Opex',
            'Plant & Machinery Item', 'Services Purchase', 'Services Sale',
            'Stock Item', 'Tools'
        ];
        $resourceNeo['productGroups'] = \App\Models\Pgroup::all()->map(function($pg) {
            return ['id' => $pg->id, 'label' => $pg->name, 'sgroup' => $pg->sgroup];
        });
        $resourceNeo['internalNames'] = \App\Models\ConsumableInternalName::all()->map(function($cin) {
            return [
                'id' => $cin->id,
                'label' => $cin->name,
                'data' => ['unitName' => $cin->unitName, 'unitAltName' => $cin->unitAltName]
            ];
        });
        $resourceNeo['units'] = \App\Models\Munit::orderBy('name')->pluck('name');

        return Inertia::render('Admin/PurchaseAddEditView', compact('resourceNeo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $formInfo = Purchase::formInfo();
        $formInfoMulti = Purchase::formInfoMulti();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];

        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
            $savedArray[$key] = $request->{$key};
        }

        foreach (array_keys($formInfoMulti) as $key) {
            $attributeNames['multi.*.' . $key] = $formInfoMulti[$key]['label'];
            isset($formInfoMulti[$key]['vRule']) && $validateRule['multi.*.' . $key] = $formInfoMulti[$key]['vRule'];
        }

        $validateRule['pur_inv'] = [
            'required',
            Rule::unique('purchases_info')->where(fn ($q) => $q->where('pur_supplier', $request->pur_supplier)),
        ];

        $request->validate($validateRule, [], $attributeNames);

        $savedArray['pur_date'] = date('Y-m-d', strtotime($request->pur_date));
        $savedArray['received_date'] = date('Y-m-d', strtotime($request->received_date));

        DB::transaction(function () use ($request, $formInfoMulti, $savedArray) {
            $purchaseInfo = PurchaseInfo::create([
                'pur_date' => $savedArray['pur_date'],
                'received_date' => $savedArray['received_date'],
                'pur_inv' => $savedArray['pur_inv'],
                'pur_supplier' => $savedArray['pur_supplier'],
                'roundoff' => $savedArray['roundoff'] ?? 0,
                'sum_total' => 0,
            ]);

            foreach ($request->multi as $ml) {
                $lineData = $savedArray;
                foreach (array_keys($formInfoMulti) as $key) {
                    if (in_array($key, ['last_rate', 'unit_rate', 'available_qty'])) {
                        continue;
                    }
                    $lineData[$key] = $ml[$key];
                }

                $lineData['pur_pr_detail'] = $ml['pur_pr_detail']['label'];
                $lineData['pur_pr_id'] = $ml['pur_pr_detail']['id'];
                $lineData['purchase_info_id'] = $purchaseInfo->id;
                $lineData['entry_type'] = 0;

                if (!empty($lineData['pur_pr_detail_int']) && !empty($lineData['pur_qty_int']) && !empty($lineData['pur_rate_int'])) {
                    $averagePriceService = new AverageUnitPriceService();
                    $averagePriceService->calculateAndUpdateAveragePrice(
                        $lineData['pur_pr_detail_int'],
                        $lineData['pur_qty_int'],
                        $lineData['pur_rate_int']
                    );
                }

                Purchase::create($lineData);
            }

            $this->syncPurchaseInfoTotals($purchaseInfo->id);
        });

        \ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->pur_supplier . ' - ' . $request->pur_inv]);

        return redirect()->route('purchase.index')->with(['message' => $this->resourceNeo['resourceTitle'] . ' Created Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Purchase $purchase)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Purchase $purchase)
    {
        $invoiceQuery = $this->getInvoiceLinesQuery($purchase);
        $invoiceLines = $invoiceQuery->orderBy('id')->get();

        if ($invoiceLines->isEmpty()) {
            return redirect()->route('purchase.index')->with(['message' => 'Purchase not found for the selected invoice.', 'msg_type' => 'danger']);
        }

        $headerRow = $invoiceLines->first();
        $purchaseInfo = $headerRow->purchaseInfo;
        $formdata = new \stdClass();
        $formdata->id = $headerRow->id;
        $formdata->pur_date = $headerRow->pur_date;
        $formdata->received_date = $headerRow->received_date;
        $formdata->pur_inv = $headerRow->pur_inv;
        $formdata->pur_supplier = $headerRow->pur_supplier;
        $formdata->roundoff = $purchaseInfo ? $purchaseInfo->roundoff : 0;
        $formdata->multi = [];

        $formInfoMulti = Purchase::formInfoMulti();

        foreach ($invoiceLines as $line) {
            $temp = [];
            foreach (array_keys($formInfoMulti) as $key) {
                $temp[$key] = $line->{$key};
            }
            $temp['id'] = $line->id;
            $temp['pur_pr_id'] = $line->pur_pr_id;
            $formdata->multi[] = $temp;
        }

        $resourceNeo = $this->resourceNeo;
        $resourceNeo['Multilabel'] = 'Line items';
        $resourceNeo['fColumn'] = 5;
        $resourceNeo['fColumnMulti'] = 8;
        $resourceNeo['AllowMore'] = true;
        $resourceNeo['AllowDel'] = true;
        $resourceNeo['formInfo'] = Purchase::formInfo();
        $resourceNeo['formInfoMulti'] = $formInfoMulti;
        
        $resourceNeo['productSubgroups'] = [
            'Capex', 'Consumable Item', 'Indirect Expense/Purchase', 'Opex',
            'Plant & Machinery Item', 'Services Purchase', 'Services Sale',
            'Stock Item', 'Tools'
        ];
        $resourceNeo['productGroups'] = \App\Models\Pgroup::all()->map(function($pg) {
            return ['id' => $pg->id, 'label' => $pg->name, 'sgroup' => $pg->sgroup];
        });
        $resourceNeo['internalNames'] = \App\Models\ConsumableInternalName::all()->map(function($cin) {
            return [
                'id' => $cin->id,
                'label' => $cin->name,
                'data' => ['unitName' => $cin->unitName, 'unitAltName' => $cin->unitAltName]
            ];
        });
        $resourceNeo['units'] = \App\Models\Munit::orderBy('name')->pluck('name');

        return Inertia::render('Admin/PurchaseAddEditView', compact('formdata', 'resourceNeo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Purchase $purchase)
    {
        $formInfo = Purchase::formInfo();
        $formInfoMulti = Purchase::formInfoMulti();
        $attributeNames = [];
        $validateRule = [];

        $purchaseInfo = $purchase->purchaseInfo;
        $purchaseInfoId = $purchaseInfo?->id;

        foreach (array_keys($formInfo) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }

        $validateRule['pur_inv'] = [
            'required',
            Rule::unique('purchases_info')->where(fn ($q) => $q->where('pur_supplier', $request->pur_supplier))->ignore($purchaseInfoId),
        ];

        foreach (array_keys($formInfoMulti) as $key) {
            $attributeNames['multi.*.' . $key] = $formInfoMulti[$key]['label'];
            isset($formInfoMulti[$key]['vRule']) && $validateRule['multi.*.' . $key] = $formInfoMulti[$key]['vRule'];
        }

        $request->validate($validateRule, [], $attributeNames);

        $headerData = [
            'pur_date' => date('Y-m-d', strtotime($request->pur_date)),
            'received_date' => date('Y-m-d', strtotime($request->received_date)),
            'pur_inv' => $request->pur_inv,
            'pur_supplier' => $request->pur_supplier,
            'roundoff' => $request->roundoff ?? 0,
        ];

        DB::transaction(function () use ($request, $purchase, $purchaseInfo, $formInfoMulti, $headerData) {
            if (!$purchaseInfo) {
                $purchaseInfo = PurchaseInfo::create(array_merge($headerData, ['sum_total' => 0]));
            } else {
                $purchaseInfo->update($headerData);
            }

            $invoiceQuery = $this->getInvoiceLinesQuery($purchase);
            $existingLines = $invoiceQuery->get()->keyBy('id');
            $savedIds = [];

            foreach ($request->multi as $ml) {
                $lineData = $headerData;

                foreach (array_keys($formInfoMulti) as $key) {
                    if (in_array($key, ['last_rate', 'unit_rate', 'available_qty'])) {
                        continue;
                    }
                    $lineData[$key] = $ml[$key];
                }

                $lineData['pur_pr_detail'] = $ml['pur_pr_detail']['label'];
                $lineData['pur_pr_id'] = $ml['pur_pr_detail']['id'];
                $lineData['purchase_info_id'] = $purchaseInfo->id;

                $lineId = isset($ml['id']) ? (int) $ml['id'] : 0;

                if ($lineId > 0 && $existingLines->has($lineId)) {
                    if (!empty($lineData['pur_pr_detail_int']) && !empty($lineData['pur_qty_int']) && !empty($lineData['pur_rate_int'])) {
                        $averagePriceService = new AverageUnitPriceService();
                        $averagePriceService->calculateAndUpdateAveragePrice(
                            $lineData['pur_pr_detail_int'],
                            $lineData['pur_qty_int'],
                            $lineData['pur_rate_int'],
                            $lineId
                        );
                    }

                    $existingLines[$lineId]->update($lineData);
                    $savedIds[] = $lineId;
                    continue;
                }

                if (!empty($lineData['pur_pr_detail_int']) && !empty($lineData['pur_qty_int']) && !empty($lineData['pur_rate_int'])) {
                    $averagePriceService = new AverageUnitPriceService();
                    $averagePriceService->calculateAndUpdateAveragePrice(
                        $lineData['pur_pr_detail_int'],
                        $lineData['pur_qty_int'],
                        $lineData['pur_rate_int']
                    );
                }

                $newLine = Purchase::create($lineData);
                $savedIds[] = $newLine->id;
            }

            $toDeleteIds = array_values(array_diff($existingLines->keys()->all(), $savedIds));
            if (!empty($toDeleteIds)) {
                Purchase::whereIn('id', $toDeleteIds)->delete();
            }

            if (Purchase::where('purchase_info_id', $purchaseInfo->id)->where('entry_type', 0)->exists()) {
                $this->syncPurchaseInfoTotals($purchaseInfo->id);
            } else {
                $purchaseInfo->delete();
            }
        });

        \ActivityLog::add(['action' => 'updated', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->pur_supplier . ' - ' . $request->pur_inv]);

        return redirect()->route('purchase.index')->with(['message' => 'Purchase Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase)
    {
        $invoiceQuery = $this->getInvoiceLinesQuery($purchase);
        $invoiceLines = $invoiceQuery->get();

        if ($invoiceLines->isEmpty()) {
            return redirect()->back()->with('message', 'Purchase Deleted !!');
        }

        $purchaseInfoId = $invoiceLines->first()->purchase_info_id;
        $lineIds = $invoiceLines->pluck('id')->all();

        if (!empty($purchaseInfoId)) {
            PurchaseInfo::where('id', $purchaseInfoId)->delete();
        }

        \ActivityLog::add(['action' => 'deleted', 'module' => 'purchase', 'data_key' => implode(',', $lineIds)]);

        return redirect()->back()->with('message', 'Purchase Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        $purchaseIds = request('ids', []);

        $purchases = Purchase::whereIn('id', $purchaseIds)->get();
        $groupMap = [];

        foreach ($purchases as $purchase) {
            $groupKey = 'info:' . $purchase->purchase_info_id;
            $groupMap[$groupKey] = $purchase;
        }

        $allDeletedLineIds = [];

        foreach ($groupMap as $purchase) {
            $invoiceLines = $this->getInvoiceLinesQuery($purchase)->get();
            if ($invoiceLines->isEmpty()) {
                continue;
            }

            $purchaseInfoId = $invoiceLines->first()->purchase_info_id;
            $lineIds = $invoiceLines->pluck('id')->all();

            if (!empty($purchaseInfoId)) {
                PurchaseInfo::where('id', $purchaseInfoId)->delete();
            }

            $allDeletedLineIds = array_merge($allDeletedLineIds, $lineIds);
        }

        $uname = (count($allDeletedLineIds) > 50) ? 'Many' : implode(',', $allDeletedLineIds);
        \ActivityLog::add(['action' => 'deleted', 'module' => 'purchase', 'data_key' => $uname]);

        return redirect()->back()->with('message', 'Selected Purchase Deleted !!');
    }

    /**
     * Generate barcodes for purchase items
     */
    public function generateBarcode(Purchase $purchase)
    {
        $quantity = $purchase->pur_qty_int_alt;
        $internalName = $purchase->pur_pr_detail_int;

        $productInfo = \App\Models\Product::select('pgroups.name as group_name')
            ->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo')
            ->where('products.id', $purchase->pur_pr_id)
            ->first();

        return Inertia::render('Admin/BarcodePrint', [
            'barcodeData' => [
                'quantity' => $quantity,
                'id' => $purchase->id,
                'internalName' => $internalName,
                'invoiceID' => $purchase->pur_inv,
                'pur_incharge' => $purchase->pur_incharge,
                'pur_loc' => $purchase->pur_loc,
                'unit' => $purchase->pur_unint_int_alt,
                'group_name' => $productInfo->group_name,
            ]
        ]);
    }

    public function detailView(Purchase $purchase)
    {
        $invoiceLines = $this->getInvoiceLinesQuery($purchase)->orderBy('id')->get();
        if ($invoiceLines->isEmpty()) {
            return response()->json(['header' => null, 'columns' => [], 'items' => []]);
        }

        $formInfo = Purchase::formInfo();
        $formInfoMulti = Purchase::formInfoMulti();
        $columns = [];
        foreach ($formInfo as $key => $meta) {
            if (!($meta['hidden'] ?? false) && $key !== 'roundoff') {
                $columns[] = ['key' => $key, 'label' => $meta['label'], 'align' => $meta['align'] ?? 'left'];
            }
        }
        foreach ($formInfoMulti as $key => $meta) {
            if (in_array($key, ['last_rate', 'unit_rate', 'available_qty'])) {
                continue;
            }
            if (!($meta['hidden'] ?? false)) {
                $columns[] = ['key' => $key, 'label' => $meta['label'], 'align' => $meta['align'] ?? 'left'];
            }
        }
        $columns[] = ['key' => 'groupinfo_name', 'label' => 'Prod Group', 'align' => 'left'];
        $columns[] = ['key' => 'groupinfo_sname', 'label' => 'Used Type', 'align' => 'left'];
        $columns[] = ['key' => 'barcode_link', 'label' => 'Barcode', 'align' => 'left'];

        $groupByProduct = DB::table('products')
            ->leftJoin('pgroups', 'pgroups.id', '=', 'products.groupinfo')
            ->whereIn('products.id', $invoiceLines->pluck('pur_pr_id')->filter()->unique()->values()->all())
            ->select('products.id as product_id', 'pgroups.name as groupinfo_name', 'pgroups.sgroup as groupinfo_sname')
            ->get()
            ->keyBy('product_id');

        $headerRow = $invoiceLines->first();
        $items = $invoiceLines->map(function ($line) use ($groupByProduct) {
            $item = $line->toArray();
            unset($item['created_at'], $item['updated_at']);
            $groupInfo = $groupByProduct[$line->pur_pr_id] ?? null;
            $item['groupinfo_name'] = $groupInfo->groupinfo_name ?? '';
            $item['groupinfo_sname'] = $groupInfo->groupinfo_sname ?? '';
            $item['barcode_link'] = route('purchase.barcode', $line->id);
            return $item;
        })->values();

        return response()->json([
            'header' => [
                'pur_inv' => $headerRow->pur_inv,
                'pur_date' => $headerRow->pur_date,
                'received_date' => $headerRow->received_date,
                'pur_supplier' => $headerRow->pur_supplier,
                'roundoff' => $headerRow->purchaseInfo?->roundoff ?? 0,
                'sum_total' => $headerRow->purchaseInfo?->sum_total ?? $invoiceLines->sum('pur_amnt_total'),
                'item_count' => $invoiceLines->count(),
            ],
            'columns' => $columns,
            'items' => $items,
        ]);
    }

    protected function getInvoiceLinesQuery(Purchase $purchase)
    {
        return Purchase::query()
            ->where('entry_type', 0)
            ->where('purchase_info_id', $purchase->purchase_info_id);
    }

    protected function syncPurchaseInfoTotals(int $purchaseInfoId): void
    {
        $sumTotal = (float) Purchase::where('entry_type', 0)
            ->where('purchase_info_id', $purchaseInfoId)
            ->sum('pur_amnt_total');

        $purchaseInfo = PurchaseInfo::find($purchaseInfoId);
        if ($purchaseInfo) {
            $sumTotal += (float) $purchaseInfo->roundoff;
            $purchaseInfo->update([
                'sum_total' => $sumTotal,
            ]);
        }
    }
}
