<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use App\Services\AverageUnitPriceService;


class PurchaseController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'purchase', 'resourceTitle' => 'Purchase Master', 'iconPath' => 'M18.6,6.62C17.16,6.62 15.8,7.18 14.83,8.15L7.8,14.39C7.16,15.03 6.31,15.38 5.4,15.38C3.53,15.38 2,13.87 2,12C2,10.13 3.53,8.62 5.4,8.62C6.31,8.62 7.16,8.97 7.84,9.65L8.97,10.65L10.5,9.31L9.22,8.2C8.2,7.18 6.84,6.62 5.4,6.62C2.42,6.62 0,9.04 0,12C0,14.96 2.42,17.38 5.4,17.38C6.84,17.38 8.2,16.82 9.17,15.85L16.2,9.61C16.84,8.97 17.69,8.62 18.6,8.62C20.47,8.62 22,10.13 22,12C22,13.87 20.47,15.38 18.6,15.38C17.7,15.38 16.84,15.03 16.16,14.35L15,13.34L13.5,14.68L14.78,15.8C15.8,16.81 17.15,17.37 18.6,17.37C21.58,17.37 24,14.96 24,12C24,9 21.58,6.62 18.6,6.62Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:purchase_list', ['only' => ['index', 'show']]);
        $this->middleware('can:purchase_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:purchase_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:purchase_delete', ['only' => ['destroy']]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo = Purchase::formInfo();
        $formInfoMulti = Purchase::formInfoMulti();
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    foreach (array_keys($formInfo) as $key) {
                        $query->orWhere($key, 'LIKE', "%{$value}%");
                    }
                    foreach (array_keys($formInfoMulti) as $key) {
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
        $query = Purchase::select('purchases.*', 'pgroups.name as groupinfo_name', 'pgroups.sgroup as groupinfo_sname')->where('entry_type', 0)->leftJoin('products', 'products.id', 'purchases.pur_pr_id', 'left')->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo', 'left');

        $query->inFinancialYear();

        if (\Auth::user()->can('all') || \Auth::user()->can('purchase_list_for_all')) {
        } else {
            $query = $query->where('pur_incharge', \Auth::user()->name);
        }

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('-pur_date')
            ->allowedSorts(array_merge(array_diff(array_keys($formInfo), []), array_keys($formInfoMulti), []))
            ->allowedFilters(array_merge($filter_array, [AllowedFilter::scope('pur_date_start'), AllowedFilter::scope('pur_date_end'), AllowedFilter::scope('received_date_start'), AllowedFilter::scope('received_date_end'), AllowedFilter::exact('groupinfo_sname', 'pgroups.sgroup'), $globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (\Auth::user()->can('purchase_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (\Auth::user()->can('purchase_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }
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
            $arrKey = array_diff(array_keys($formInfo), []);
            foreach ($arrKey as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false, extra: ['type' => $formInfo[$key]['type'] ?? '', 'options' => [], 'align' => $formInfo[$key]['align'] ?? 'left', 'showTotal' => $formInfo[$key]['showTotal'] ?? false]);
            }
            foreach (array_keys($formInfoMulti) as $key) {
                $table->column($key, $formInfoMulti[$key]['label'], searchable: $formInfoMulti[$key]['searchable'] ?? false, sortable: $formInfoMulti[$key]['sortable'] ?? false, hidden: $formInfoMulti[$key]['hidden'] ?? false, extra: ['align' => $formInfoMulti[$key]['align'] ?? 'left', 'showTotal' => $formInfoMulti[$key]['showTotal'] ?? false]);
            }
            $fresult = [];
            foreach ($formInfo['pur_supplier']['options'] as  $opt) {
                $opt && $fresult[$opt] = $opt;
            }
            $fresult2 = [];
            foreach ($formInfoMulti['pur_incharge']['options'] as  $opt) {
                $opt && $fresult2[$opt] = $opt;
            }
            $fresult3 = [];
            foreach ($formInfoMulti['pur_unit']['options'] as  $opt) {
                $opt && $fresult3[$opt] = $opt;
            }
            $fresult4 = [];
            foreach ($formInfoMulti['pur_loc']['options'] as  $opt) {
                $opt && $fresult4[$opt] = $opt;
            }
            $fresult5 = [];
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
            if (\Auth::user()->can('all') || \Auth::user()->can('purchase_list_for_all')) {
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
        $resourceNeo['fColumn'] = 4;
        $resourceNeo['fColumnMulti'] = 8;
        $resourceNeo['AllowMore'] = true;
        $resourceNeo['AllowDel'] = true;
        $resourceNeo['formInfo'] = Purchase::formInfo();
        $resourceNeo['formInfoMulti'] = Purchase::formInfoMulti();
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

        $request->validate($validateRule, [], $attributeNames);
        $savedArray['pur_date'] = date('Y-m-d', strtotime($request->pur_date));
        $savedArray['received_date'] = date('Y-m-d', strtotime($request->received_date));


        foreach ($request->multi as $ml) {
            foreach (array_keys($formInfoMulti) as $key) {
                $savedArray[$key] = $ml[$key];
            }
            $savedArray['pur_pr_detail'] = $ml['pur_pr_detail']['label'];
            $savedArray['pur_pr_id'] = $ml['pur_pr_detail']['id'];

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
        $formdata = $purchase;
        $temp = [];
        $formInfoMulti = Purchase::formInfoMulti();
        foreach (array_keys($formInfoMulti) as $key) {
            $temp[$key] = $purchase->{$key};
        }
        $formdata->multi = [$temp];
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['Multilabel'] = 'Line items';
        $resourceNeo['fColumn'] = 4;
        $resourceNeo['fColumnMulti'] = 8;
        $resourceNeo['AllowMore'] = false;
        $resourceNeo['AllowDel'] = false;
        $resourceNeo['formInfo'] = Purchase::formInfo();
        $resourceNeo['formInfoMulti'] = $formInfoMulti;
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
        foreach (array_diff(array_keys($formInfo), ['pur_inv']) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        foreach (array_keys($formInfoMulti) as $key) {
            $attributeNames['multi.*.' . $key] = $formInfoMulti[$key]['label'];
            isset($formInfoMulti[$key]['vRule']) && $validateRule['multi.*.' . $key] = $formInfoMulti[$key]['vRule'];
        }
        $request->validate($validateRule, [], $attributeNames);
        foreach (array_diff(array_keys($formInfo), []) as $key) {
            $purchase->{$key} = $request->{$key};
        }
        $purchase->pur_date = date('Y-m-d', strtotime($request->pur_date));
        $purchase->received_date = date('Y-m-d', strtotime($request->received_date));
        foreach ($request->multi as $ml) {
            foreach (array_keys($formInfoMulti) as $key) {
                $purchase->{$key} = $ml[$key];
            }
            $purchase->pur_pr_detail = $ml['pur_pr_detail']['label'];
            $purchase->pur_pr_id = $ml['pur_pr_detail']['id'];
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

        return redirect()->route('purchase.index')->with(['message' => 'Purchase Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase)
    {
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
}
