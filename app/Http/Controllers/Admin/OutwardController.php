<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Outward;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Services\OpenStockService;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Illuminate\Support\Facades\DB;

class OutwardController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'outward', 'resourceTitle' => 'Outward', 'iconPath' => 'M18.6,6.62C17.16,6.62 15.8,7.18 14.83,8.15L7.8,14.39C7.16,15.03 6.31,15.38 5.4,15.38C3.53,15.38 2,13.87 2,12C2,10.13 3.53,8.62 5.4,8.62C6.31,8.62 7.16,8.97 7.84,9.65L8.97,10.65L10.5,9.31L9.22,8.2C8.2,7.18 6.84,6.62 5.4,6.62C2.42,6.62 0,9.04 0,12C0,14.96 2.42,17.38 5.4,17.38C6.84,17.38 8.2,16.82 9.17,15.85L16.2,9.61C16.84,8.97 17.69,8.62 18.6,8.62C20.47,8.62 22,10.13 22,12C22,13.87 20.47,15.38 18.6,15.38C17.7,15.38 16.84,15.03 16.16,14.35L15,13.34L13.5,14.68L14.78,15.8C15.8,16.81 17.15,17.37 18.6,17.37C21.58,17.37 24,14.96 24,12C24,9 21.58,6.62 18.6,6.62Z', 'actions' => ['c', 'r', 'u', 'd']];

    public function __construct()
    {
        $this->middleware('can:outward_list', ['only' => ['index', 'show']]);
        $this->middleware('can:outward_delete', ['only' => ['destroy', 'bulkDestroy']]);
        $this->middleware('can:outward_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:outward_delete', ['only' => ['destroy']]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo = Outward::formInfo();
        $formInfoMulti = Outward::formInfoMulti();
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
        if (\Auth::user()->can('all') || \Auth::user()->can('outward_list_for_all')) {
            $query = Outward::select('outwards.*', 'pgroups.name as groupinfo_name', 'pgroups.sgroup as groupinfo_sname', DB::raw('out_qty * IFNULL(unitPrice, 0) as OutwardValue'))
                ->leftJoin('products', 'products.id', 'outwards.out_product_id', 'left')
                ->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo', 'left');
            //$resourceNeo['formInfo']['out_incharge']['type'] = null;
        } else {
            $query = Outward::select('outwards.*', 'pgroups.name as groupinfo_name', 'pgroups.sgroup as groupinfo_sname', DB::raw('out_qty * IFNULL(unitPrice, 0) as OutwardValue'))
                ->leftJoin('products', 'products.id', 'outwards.out_product_id', 'left')
                ->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo', 'left')
                ->where('out_incharge', Auth::user()->name);
        }

        //$query->inFinancialYear();

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('-out_date')
            ->allowedSorts(array_merge(array_diff(array_keys($formInfo), []), array_keys($formInfoMulti), ['OutwardValue']))
            ->allowedFilters(array_merge($filter_array, [AllowedFilter::scope('out_date_start'), AllowedFilter::scope('out_date_end'), AllowedFilter::exact('groupinfo_name', 'pgroups.name'), AllowedFilter::exact('groupinfo_sname', 'pgroups.sgroup'), $globalSearch]))
            ->paginate($perPage)
            ->withQueryString();

        if (\Auth::user()->can('outward_delete')) {
            $this->resourceNeo['bulkActions'] = ['bulk_delete' => []];
        }

        if (\Auth::user()->can('outward_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }
        $this->resourceNeo['extraLinks'] = [];
        $this->resourceNeo['extraMainLinks'] = [['link' => 'outward.scan', 'label' => 'Scan', 'icon' => 'M3,11H5V13H3V11M11,5H13V9H11V5M9,11H13V15H11V13H9V11M15,11H17V13H19V11H21V13H19V15H21V19H19V21H17V19H13V21H11V17H15V15H17V13H15V11M19,19V15H17V19H19M15,3H21V9H15V3M17,5V7H19V5H17M3,3H9V9H3V3M5,5V7H7V5H5M3,15H9V21H3V15M5,17V19H7V17H5Z']];

        $this->resourceNeo['showall'] = true;

        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo, $formInfoMulti) {
            $table->withGlobalSearch();
            $arrKey = array_diff(array_keys($formInfo), []);
            foreach ($arrKey as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false, extra: ['type' => $formInfo[$key]['type'] ?? '', 'options' => [], 'align' => $formInfo[$key]['align'] ?? 'left', 'showTotal' => $formInfo[$key]['showTotal'] ?? false]);
            }
            foreach (array_diff(array_keys($formInfoMulti), ['out_remark']) as $key) {
                $table->column($key, $formInfoMulti[$key]['label'], searchable: $formInfoMulti[$key]['searchable'] ?? false, sortable: $formInfoMulti[$key]['sortable'] ?? false, hidden: $formInfoMulti[$key]['hidden'] ?? false, extra: ['align' => $formInfoMulti[$key]['align'] ?? 'left', 'showTotal' => $formInfoMulti[$key]['showTotal'] ?? false]);
            }
            $table->column('OutwardValue', 'Outward Value', sortable: true, extra: ['align' => 'right', 'showTotal' => true]);
            $table->column('out_remark', 'Remark', sortable: true);

            $fresult2 = [];
            foreach ($formInfoMulti['out_incharge']['options'] as  $opt) {
                $opt && $fresult2[$opt] = $opt;
            }
            $fresult6 = [];
            foreach ($formInfoMulti['out_product_group']['options'] as  $opt) {
                if (isset($opt['label'])) {
                    $fresult6[$opt['label']] = $opt['label'];
                }
            }
            $fresult7 = [];
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
                $opt && $fresult7[$opt] = $opt;
            }
            $fresult3 = [];
            foreach ($formInfoMulti['out_qty_unit']['options'] as  $opt) {
                $opt && $fresult3[$opt] = $opt;
            }
            $fresult4 = [];
            foreach ($formInfoMulti['out_loc']['options'] as  $opt) {
                $opt && $fresult4[$opt] = $opt;
            }
            $table
                ->column(label: 'Used Type', key: 'groupinfo_sname')
                ->column(label: 'Actions')
                ->dateFilter(key: 'out_date_start', label: 'Date From')
                ->dateFilter(key: 'out_date_end', label: 'Date To');
            if (\Auth::user()->can('all') || \Auth::user()->can('outward_list_for_all')) {
                $table->selectFilter(key: 'out_incharge', label: $formInfoMulti['out_incharge']['label'], options: $fresult2, noFilterOptionLabel: 'All');
            }
            $table->selectFilter(key: 'out_qty_unit', label: $formInfoMulti['out_qty_unit']['label'], options: $fresult3, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'out_qty_unit_alt', label: $formInfoMulti['out_qty_unit_alt']['label'], options: $fresult3, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'out_loc', label: $formInfoMulti['out_loc']['label'], options: $fresult4, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'out_product_group', label: $formInfoMulti['out_product_group']['label'], options: $fresult6, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'groupinfo_sname', label: 'Used Type', options: $fresult7, noFilterOptionLabel: 'All')

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
        $resourceNeo['formInfo'] = Outward::formInfo();
        $resourceNeo['formInfoMulti'] = Outward::formInfoMulti();

        $resourceNeo['formInfoMulti']['balance'] = ['label' => 'Balance', 'readonly' => true, ];

        if (!(\Auth::user()->can('all') || \Auth::user()->can('outward_add_for_all'))) {
            $resourceNeo['formInfoMulti']['out_incharge']['type'] = null;
            $resourceNeo['formInfoMulti']['out_incharge']['readonly'] = true;

            $resourceNeo['formInfoMulti']['out_incharge']['default'] = Auth::user()->name;
            $resourceNeo['formInfoMulti']['out_loc']['options'] =
                Purchase::select('pur_loc')->where('pur_incharge', Auth::user()->name)->orderBy('pur_loc')->groupBy('pur_loc')->get()->pluck('pur_loc');

            $allGDatas = Purchase::select('pgroups.id', 'pgroups.name', 'pgroups.sgroup')
                ->where('pur_incharge', Auth::user()->name)
                ->leftJoin('products', 'products.id', '=', 'purchases.pur_pr_id')
                ->leftJoin('pgroups', 'pgroups.id', '=', 'products.groupinfo')
                ->groupBy('pgroups.id', 'pgroups.name', 'pgroups.sgroup')
                ->get();

            $allopts = [];
            foreach ($allGDatas as $allData) {
                $allopts[] = ['id' => $allData->id, 'label' => $allData->name . " (" . $allData->sgroup . ")"];
            }
            $resourceNeo['formInfoMulti']['out_product_group']['options'] = $allopts;
        }
        return Inertia::render('Admin/OutwardAddEditView', compact('resourceNeo'));
    }

    public function scan()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['Multilabel'] = 'Line items';
        $resourceNeo['fColumn'] = 3;
        $resourceNeo['fColumnMulti'] = 8;
        $resourceNeo['AllowMore'] = true;
        $resourceNeo['AllowDel'] = true;
        $resourceNeo['formInfo'] = Outward::formInfo();
        $resourceNeo['formInfoMulti'] = Outward::formInfoMulti();
        
        $resourceNeo['formInfoMulti']['balance'] = ['label' => 'Balance', 'readonly' => true, ];
        
        // Fetch all products and pass directly (no AJAX needed since no dependencies)
        $allProducts = $this->getAllProductsData();
        $resourceNeo['formInfoMulti']['out_product']['options'] = $allProducts;
        
        if (!(\Auth::user()->can('all') || \Auth::user()->can('outward_add_for_all'))) {
            // Make incharge readonly and prepopulated for restricted users
            $resourceNeo['formInfoMulti']['out_incharge']['type'] = null;
            $resourceNeo['formInfoMulti']['out_incharge']['readonly'] = true;
            $resourceNeo['formInfoMulti']['out_incharge']['default'] = Auth::user()->name;
            
            // Location options will be filtered by incharge via API
            $resourceNeo['formInfoMulti']['out_loc']['options'] = [];
            
            // Product group options will be filtered via API
            $resourceNeo['formInfoMulti']['out_product_group']['options'] = [];
        }

        return Inertia::render('Admin/Scan', compact('resourceNeo'));
    }
    
    /**
     * Get all products data (helper method used by both scan controller and AJAX endpoint)
     */
    private function getAllProductsData()
    {
        $query = Purchase::select(
                'purchases.pur_pr_detail_int', 
                DB::raw('MAX(purchases.pur_pr_id) as pur_pr_id'),
                DB::raw('MAX(purchases.pur_unint_int) as pur_unint_int'),
                DB::raw('MAX(purchases.pur_unint_int_alt) as pur_unint_int_alt'),
                DB::raw('MAX(purchases.pur_qty_int) as pur_qty_int'),
                DB::raw('MAX(purchases.pur_qty_int_alt) as pur_qty_int_alt'),
                'consumable_internal_names.unitPrice as pur_unit_price',
                'consumable_internal_names.unitName',
                'consumable_internal_names.unitAltName'
            )
            ->leftJoin('consumable_internal_names', 'consumable_internal_names.name', '=', 'purchases.pur_pr_detail_int')
            ->leftJoin('products', 'products.id', '=', 'purchases.pur_pr_id')
            ->leftJoin('pgroups', 'pgroups.id', '=', 'products.groupinfo')
            //->where('pgroups.sgroup', 'Stock Item')
            //->inFinancialYear()
            ->groupBy('purchases.pur_pr_detail_int', 'consumable_internal_names.unitPrice', 'consumable_internal_names.unitName', 'consumable_internal_names.unitAltName')
            ->orderBy('purchases.pur_pr_detail_int');

        // If user doesn't have full access, filter by their name
        if (!(Auth::user()->can('all') || Auth::user()->can('outward_add_for_all'))) {
            $query->where('purchases.pur_incharge', Auth::user()->name);
        }

        $products = $query->get();

        $allOpt = [];
        foreach ($products as $product) {
            $allOpt[] = [
                'id' => $product->pur_pr_id,
                'label' => $product->pur_pr_detail_int,
                'data' => [
                    'pur_unint_int' => $product->unitName ?? $product->pur_unint_int,
                    'pur_unint_int_alt' => $product->unitAltName ?? $product->pur_unint_int_alt,
                    'pur_qty_int' => $product->pur_qty_int,
                    'pur_qty_int_alt' => $product->pur_qty_int_alt,
                    'pur_unit_price' => $product->pur_unit_price,
                ],
            ];
        }
        return $allOpt;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $formInfo = Outward::formInfo();
        $formInfoMulti = Outward::formInfoMulti();
        $attributeNames = [];
        $validateRule = [];
        $savedArray = [];

        if (strtotime($request->out_date) < strtotime('-2 days') && !(\Auth::user()->can('outward_back_date_entry'))) {
            return redirect()->back()->withErrors(['out_date' => 'The Out Date cannot be older than 2 days.']);
        }

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
        $savedArray['out_date'] = date('Y-m-d', strtotime($request->out_date));
        DB::beginTransaction();
        try {
            $openStockService = new OpenStockService();
            foreach ($request->multi as $ml) {
                foreach (array_diff(array_keys($formInfoMulti), []) as $key) {
                    $savedArray[$key] = $ml[$key];
                }
                $savedArray['out_product'] = $ml['out_product']['label'];
                $savedArray['out_product_id'] = $ml['out_product']['id'];
                $savedArray['out_product_group'] = $ml['out_product_group']['label'];
                $outward = Outward::create($savedArray);

                $openStockService->recordStockInFromOutward($outward);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['out_product' => $e->getMessage()])->withInput();
        }



        \ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfoMulti)[3]}]);

        return redirect()->route('outward.index')->with(['message' => $this->resourceNeo['resourceTitle'] . ' Created Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Outward $outward)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Outward $outward)
    {
        $formdata = $outward;
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['Multilabel'] = 'Line items';
        $resourceNeo['fColumn'] = 5;
        $resourceNeo['fColumnMulti'] = 8;
        $resourceNeo['AllowMore'] = false;
        $resourceNeo['AllowDel'] = false;
        $resourceNeo['formInfo'] = Outward::formInfo();
        $formInfoMulti = Outward::formInfoMulti();



        $temp = [];
        foreach (array_keys($formInfoMulti) as $key) {
            $temp[$key] = $formdata->{$key};
        }
        $temp['out_product_group_id'] = Product::select('groupinfo')->where('id', $outward->out_product_id)->pluck('groupinfo');
        $temp['out_product_id'] = $formdata->out_product_id;
        $formdata->multi = [$temp];

        $resourceNeo['formInfoMulti'] = $formInfoMulti;

        $resourceNeo['formInfoMulti']['balance'] = ['label' => 'Balance', 'readonly' => true, ];

        if (!(\Auth::user()->can('all') || \Auth::user()->can('outward_add_for_all'))) {
            $resourceNeo['formInfoMulti']['out_incharge']['type'] = null;
        }
        return Inertia::render('Admin/OutwardAddEditView', compact('formdata', 'resourceNeo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Outward $outward)
    {
        $formInfo = Outward::formInfo();
        $formInfoMulti = Outward::formInfoMulti();
        $attributeNames = [];
        $validateRule = [];
        foreach (array_diff(array_keys($formInfo), ['out_inv']) as $key) {
            $attributeNames[$key] = $formInfo[$key]['label'];
            isset($formInfo[$key]['vRule']) && $validateRule[$key] = $formInfo[$key]['vRule'];
        }
        foreach (array_keys($formInfoMulti) as $key) {
            $attributeNames['multi.*.' . $key] = $formInfoMulti[$key]['label'];
            isset($formInfoMulti[$key]['vRule']) && $validateRule['multi.*.' . $key] = $formInfoMulti[$key]['vRule'];
        }
        $request->validate($validateRule, [], $attributeNames);
        foreach (array_diff(array_keys($formInfo), []) as $key) {
            $outward->{$key} = $request->{$key};
        }
        $outward->out_date = date('Y-m-d', strtotime($request->out_date));

        foreach ($request->multi as $ml) {
            foreach (array_diff(array_keys($formInfoMulti), []) as $key) {
                $outward->{$key} = $ml[$key];
            }

            $temp = $outward->out_product;
            $outward->out_product = $temp['label'];
            $outward->out_product_id = $temp['id'];
            $outward->out_product_group = $outward->out_product_group['label'];
        }

        $outward->save();

        \ActivityLog::add(['action' => 'updated', 'module' => $this->resourceNeo['resourceName'], 'data_key' => $request->{array_keys($formInfoMulti)[3]}]);

        return redirect()->route('outward.index')->with(['message' => 'Outward Updated Successfully !!', 'msg_type' => 'info']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Outward $outward)
    {
        $uname = $outward->id;
        $outward->delete();
        \ActivityLog::add(['action' => 'deleted', 'module' => 'outward', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Outward Deleted !!');
    }

    /**
     * bulk delete.
     */
    public function bulkDestroy()
    {
        Outward::whereIn('id', request('ids'))->delete();
        $uname = (count(request('ids')) > 50) ? 'Many' : $uname = implode(',', request('ids'));
        \ActivityLog::add(['action' => 'deleted', 'module' => 'outward', 'data_key' => $uname]);
        return redirect()->back()->with('message', 'Selected Outward Deleted !!');
    }


    public function products(Request $request)
    {
        $incharge = $request->out_incharge;
        $location = $request->out_loc;
        $groupId = $request->out_product_group;

        // 1. Calculate Total Outwards per Product
        $outwardsQuery = Outward::select('out_product')
            ->selectRaw('SUM(out_qty) as total_out_qty')
            ->where('out_incharge', $incharge)
            ->where('out_loc', $location)
            //->inFinancialYear()
            ->groupBy('out_product');

        // 2. Calculate Current Stock per Product (Total Purchases - Total Outwards)
        $stockSubQuery = Purchase::select('purchases.pur_pr_detail_int')
            ->selectRaw('IFNULL(SUM(purchases.pur_qty_int) - IFNULL(outwards_sum.total_out_qty, 0), 0) as current_stock')
            ->where('purchases.pur_incharge', $incharge)
            ->where('purchases.pur_loc', $location)
            ->leftJoinSub($outwardsQuery, 'outwards_sum', 'purchases.pur_pr_detail_int', '=', 'outwards_sum.out_product')
            //->inFinancialYear()
            ->groupBy('purchases.pur_pr_detail_int');

        // 3. Get Latest Purchase ID per Product (to ensure unique products and get latest details)
        $latestPurchasesQuery = Purchase::selectRaw('MAX(id) as max_pur_id')
            ->where('pur_incharge', $incharge)
            ->where('pur_loc', $location)
            ->groupBy('pur_pr_detail_int');

        // 4. Main Query
        $alldatas = Purchase::select(
                'purchases.pur_pr_id',
                'purchases.pur_pr_detail_int',
                'purchases.pur_unint_int',
                'purchases.pur_unint_int_alt',
                'purchases.pur_qty_int_alt',
                'purchases.pur_qty_int',
                'stock_query.current_stock',
                'consumable_internal_names.unitPrice'
            )
            // Join to get only the latest purchase record for each product
            ->joinSub($latestPurchasesQuery, 'latest_purchases', 'purchases.id', '=', 'latest_purchases.max_pur_id')
            ->leftJoin('products', 'products.id', '=', 'purchases.pur_pr_id')
            ->leftJoin('pgroups', 'pgroups.id', '=', 'products.groupinfo')
            //->where('pgroups.sgroup', 'Stock Item')
            // Join with ConsumableInternalName to get unitPrice
            ->leftJoin('consumable_internal_names', 'consumable_internal_names.name', '=', 'purchases.pur_pr_detail_int')
            // Join with Stock calculation
            ->leftJoinSub($stockSubQuery, 'stock_query', 'purchases.pur_pr_detail_int', '=', 'stock_query.pur_pr_detail_int')
            ->where('products.groupinfo', $groupId)
            //->inFinancialYear()
            ->orderBy('purchases.pur_pr_detail_int')
            ->get();

        // 5. Format Response
        $allOpt = [];
        foreach ($alldatas as $alldata) {
            $allOpt[] = ['id' => $alldata->pur_pr_id, 'label' => $alldata->pur_pr_detail_int, 'data' => $alldata];
        }
        return $allOpt;
    }

    public function productsgroup(Request $request)
    {
        $alldatas = Purchase::where('pur_incharge', $request->out_incharge)
            ->where('pur_loc', $request->out_loc)
            ->leftJoin('products', 'products.id', '=', 'purchases.pur_pr_id')
            ->leftJoin('pgroups', 'pgroups.id', '=', 'products.groupinfo')
            //->where('pgroups.sgroup', 'Stock Item')
            ->select('pgroups.id as group_id', 'pgroups.name as group_name')
            ->groupBy('pgroups.id', 'pgroups.name')
            ->orderBy('pgroups.name')
            ->get();

        $allOpt = [];
        foreach ($alldatas as $alldata) {
            $allOpt[] = ['id' => $alldata->group_id, 'label' => $alldata->group_name, 'data' => $alldata];
        }
        return $allOpt;
    }
    public function productsloc(Request $request)
    {
        return Purchase::where('pur_incharge', $request->out_incharge)
            ->select('pur_loc')
            ->distinct()
            ->orderBy('pur_loc')
            ->pluck('pur_loc');
    }

    public function productsgroup2(Request $request)
    {
        $alldatas = Purchase::where('pur_incharge', $request->out_incharge)
            ->where('pur_loc', $request->out_loc)
            ->leftJoin('products', 'products.id', '=', 'purchases.pur_pr_id')
            ->leftJoin('pgroups', 'pgroups.id', '=', 'products.groupinfo')
            //->where('pgroups.sgroup', 'Stock Item')
            ->orderBy('pgroups.name')
            /*->groupBy('pgroups.name')*/
            ->get();

        $allOpt = [];
        foreach ($alldatas as $alldata) {
            $allOpt[] = ['id' => $alldata->groupinfo, 'label' => $alldata->name, 'data' => $alldata];
        }
        return $allOpt;
    }

    /**
     * Get all products (internal names) from consumable_internal_names
     * New method for reversed dependency flow
     */
    public function getAllProducts(Request $request)
    {
        $query = Purchase::select(
                'purchases.pur_pr_detail_int', 
                DB::raw('MAX(purchases.pur_pr_id) as pur_pr_id'),
                DB::raw('MAX(purchases.pur_unint_int) as pur_unint_int'),
                DB::raw('MAX(purchases.pur_unint_int_alt) as pur_unint_int_alt'),
                DB::raw('MAX(purchases.pur_qty_int) as pur_qty_int'),
                DB::raw('MAX(purchases.pur_qty_int_alt) as pur_qty_int_alt'),
                'consumable_internal_names.unitPrice as pur_unit_price',
                'consumable_internal_names.unitName',
                'consumable_internal_names.unitAltName'
            )
            ->leftJoin('consumable_internal_names', 'consumable_internal_names.name', '=', 'purchases.pur_pr_detail_int')
            ->leftJoin('products', 'products.id', '=', 'purchases.pur_pr_id')
            ->leftJoin('pgroups', 'pgroups.id', '=', 'products.groupinfo')
            //->where('pgroups.sgroup', 'Stock Item')
            //->inFinancialYear()
            ->groupBy('purchases.pur_pr_detail_int', 'consumable_internal_names.unitPrice', 'consumable_internal_names.unitName', 'consumable_internal_names.unitAltName')
            ->orderBy('purchases.pur_pr_detail_int');

        // If user doesn't have full access, filter by their name
        if (!(Auth::user()->can('all') || Auth::user()->can('outward_add_for_all'))) {
            $query->where('purchases.pur_incharge', Auth::user()->name);
        }

        $products = $query->get();

        $allOpt = [];
        foreach ($products as $product) {
            $allOpt[] = [
                'id' => $product->pur_pr_id,
                'label' => $product->pur_pr_detail_int,
                'data' => [
                    'pur_unint_int' => $product->unitName ?? $product->pur_unint_int,
                    'pur_unint_int_alt' => $product->unitAltName ?? $product->pur_unint_int_alt,
                    'pur_qty_int' => $product->pur_qty_int,
                    'pur_qty_int_alt' => $product->pur_qty_int_alt,
                    'pur_unit_price' => $product->pur_unit_price,
                ],
            ];
        }
        return $allOpt;
    }

    /**
     * Get incharge options for a selected product
     * New method for reversed dependency flow
     */
    public function getInchargeForProduct(Request $request)
    {
        $productName = $request->out_product;

        $incharges = Purchase::where('pur_pr_detail_int', $productName)
            ->select('pur_incharge')
            ->distinct()
            //->inFinancialYear()
            ->orderBy('pur_incharge')
            ->pluck('pur_incharge');

        return $incharges;
    }

    /**
     * Get location options for a selected product (and optionally incharge)
     * New method for reversed dependency flow
     */
    public function getLocationForProduct(Request $request)
    {
        $productName = $request->out_product;
        $incharge = $request->out_incharge ?? null;

        $query = Purchase::where('pur_pr_detail_int', $productName)
            ->select('pur_loc')
            ->distinct();
            //->inFinancialYear();

        if ($incharge) {
            $query->where('pur_incharge', $incharge);
        }

        $locations = $query->orderBy('pur_loc')->pluck('pur_loc');

        return $locations;
    }

    /**
     * Get product group for a selected product
     * New method for reversed dependency flow
     */
    public function getProductGroupForProduct(Request $request)
    {
        $productName = $request->out_product;
        $incharge = $request->out_incharge ?? null;
        $location = $request->out_loc ?? null;

        $query = Purchase::where('pur_pr_detail_int', $productName)
            ->leftJoin('products', 'products.id', '=', 'purchases.pur_pr_id')
            ->leftJoin('pgroups', 'pgroups.id', '=', 'products.groupinfo')
            //->where('pgroups.sgroup', 'Stock Item')
            ->select('pgroups.id as group_id', 'pgroups.name as group_name');
            //->inFinancialYear();

        if ($incharge) {
            $query->where('pur_incharge', $incharge);
        }

        if ($location) {
            $query->where('pur_loc', $location);
        }

        $groups = $query->groupBy('pgroups.id', 'pgroups.name')
            ->orderBy('pgroups.name')
            ->get();

        $allOpt = [];
        foreach ($groups as $group) {
            $allOpt[] = ['id' => $group->group_id, 'label' => $group->group_name];
        }

        return $allOpt;
    }
}
