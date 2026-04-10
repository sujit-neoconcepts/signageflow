<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Inertia\Inertia;
use App\Models\Outward;
use App\Models\Product;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\StockThreshold;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use App\Models\Pgroup;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ActivityLog;

class StocksController extends Controller
{
    protected $resourceNeo = ['resourceName' => 'stocks', 'resourceTitle' => 'Stocks', 'iconPath' => 'M3 22V3H21V22L18 20L15 22L12 20L9 22L6 20L3 22M17 9V7H15V9H17M13 9V7H7V9H13M13 11H7V13H13V11M15 13H17V11H15V13Z', 'actions' => ['r']];

    public function __construct()
    {
        $this->middleware('can:stocks_list', ['only' => ['index', 'show']]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formInfo =
            [

                'pr_detail_int' => ['label' => 'Internal Product Name', 'searchable' => true, 'sortable' => true],
                'qty_sum_op' => ['label' => 'Qty Opn',  'align' => 'right', 'showTotal' => true],

                'qty_sum_pur' => ['label' => 'Qty Pur',  'align' => 'right', 'showTotal' => true],

                'qty_sum_out' => ['label' => 'Qty Out',  'align' => 'right', 'showTotal' => true],

                'balsum' => ['label' => 'Qty Bal', 'sortable' => true, 'align' => 'right', 'showTotal' => true],

                'pr_int_unit' => ['label' => 'Qty Unit',],
                'stock_unit_price' => ['label' => 'Stock Unit Value ', 'align' => 'right'],
                'stock_value' => ['label' => 'Stock Value',  'align' => 'right', 'showTotal' => true],

                'qty_alt_sum_op' => ['label' => 'Qty Alt Opn',  'align' => 'right', 'showTotal' => true],

                'qty_alt_sum_pur' => ['label' => 'Qty Alt Pur',  'align' => 'right', 'showTotal' => true],

                'qty_alt_sum_out' => ['label' => 'Qty Alt Out',  'align' => 'right', 'showTotal' => true],

                'balsumalt' => ['label' => 'Qty Bal Alt',   'align' => 'right', 'showTotal' => true],

                'pr_int_unit_alt' => ['label' => 'Qty Unit Alt',],
                'groupinfo_name' => ['label' => 'Prod Group', 'sortable' => true],
                'groupinfo_sname' => ['label' => 'Sub Group', 'sortable' => true],

            ];
        $formInfoMulti = [];
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    $query->orWhere('pr_detail_int', 'LIKE', "%{$value}%");
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $filter_array = [];
        foreach (array_merge(array_diff(array_keys($formInfo), []), array_keys($formInfoMulti)) as $fvalue) {
            $filter_array[] = AllowedFilter::exact($fvalue);
        }


        $inc_u = request()->query('filter');

        $subq1 = Purchase::select('pur_pr_detail_int')->selectRaw('ifnull(sum(pur_qty_int),0) as qtysum , ifnull(sum(pur_qty_int_alt),0) as qtysumalt')->where('entry_type', 0)->groupBy('pur_pr_detail_int');

        //$subq1->inFinancialYear();

        //opening
        $subq2 = Purchase::select('pur_pr_detail_int')->selectRaw('ifnull(sum(pur_qty_int),0) as qtysum , ifnull(sum(pur_qty_int_alt),0) as qtysumalt')->where('entry_type', 1)->groupBy('pur_pr_detail_int');

        //$subq2->inFinancialYear();

        $subq3 = Outward::select('out_product')->selectRaw('ifnull(sum(out_qty),0) as qtysum , ifnull(sum(out_qty_alt),0) as qtysumalt')->groupBy('out_product');

        //$subq3->inFinancialYear();

        if (\Auth::user()->can('all') || \Auth::user()->can('stocks_list_for_all')) {
            if (isset($inc_u['pur_incharge'])) {
                $subq1->where('pur_incharge', $inc_u['pur_incharge']);
                $subq2->where('pur_incharge', $inc_u['pur_incharge']);
                $subq3->where('out_incharge', $inc_u['pur_incharge']);
            }
        } else {
            $subq1->where('pur_incharge', \Auth::user()->name);
            $subq2->where('pur_incharge', \Auth::user()->name);
            $subq3->where('out_incharge', \Auth::user()->name);
        }

        if (isset($inc_u['pur_loc'])) {
            $subq1->where('pur_loc', $inc_u['pur_loc']);
            $subq2->where('pur_loc', $inc_u['pur_loc']);
            $subq3->where('out_loc', $inc_u['pur_loc']);
        }

        if (isset($inc_u['stock_date'])) {
            $subq1->where('pur_date', '<=', $inc_u['stock_date']);
            $subq2->where('pur_date', '<=', $inc_u['stock_date']);
            $subq3->where('out_date', '<=', $inc_u['stock_date']);
        }

        $query = Product::select('products.pr_detail_int')
            ->selectRaw('MAX(products.id) as id')
            ->selectRaw('MAX(pr_int_unit_alt) as pr_int_unit_alt')
            ->selectRaw('MAX(pr_int_unit) as pr_int_unit')
            ->selectRaw('MAX(pgroups.name) as groupinfo_name')
            ->selectRaw('MAX(consumable_internal_names.unitPrice) as stock_unit_price')
            ->selectRaw('ROUND(MAX(consumable_internal_names.unitPrice) * (ifnull(MAX(subq2.qtysum),0)+ifnull(MAX(subq1.qtysum),0)-ifnull(MAX(subq3.qtysum),0)), 2) as stock_value')
            ->selectRaw('MAX(pgroups.sgroup) as groupinfo_sname')
            ->selectRaw('ifnull(MAX(subq2.qtysum),0) as qty_sum_op , ifnull(MAX(subq1.qtysum),0) as qty_sum_pur , ifnull(MAX(subq3.qtysum),0) as qty_sum_out , ifnull(MAX(subq2.qtysumalt),0) as qty_alt_sum_op, ifnull(MAX(subq1.qtysumalt),0) as qty_alt_sum_pur, ifnull(MAX(subq3.qtysumalt),0) as qty_alt_sum_out')

            ->selectRaw('ifnull(MAX(subq2.qtysum),0)+ifnull(MAX(subq1.qtysum),0)-ifnull(MAX(subq3.qtysum),0) as balsum, ifnull(MAX(subq2.qtysumalt),0)+ifnull(MAX(subq1.qtysumalt),0)-ifnull(MAX(subq3.qtysumalt),0) as balsumalt')

            ->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo', 'left')
            ->leftJoin('consumable_internal_names', 'consumable_internal_names.name', '=', 'products.pr_detail_int')
            ->leftJoinSub($subq1, 'subq1', function ($join) {
                $join->on('products.pr_detail_int', '=', 'subq1.pur_pr_detail_int');
            })
            ->leftJoinSub($subq2, 'subq2', function ($join) {
                $join->on('products.pr_detail_int', '=', 'subq2.pur_pr_detail_int');
            })
            ->leftJoinSub($subq3, 'subq3', function ($join) {
                $join->on('products.pr_detail_int', '=', 'subq3.out_product');
            })

            ->where(
                function ($query) {
                    $query->where('subq1.qtysum', '>', 0)->orWhere('subq2.qtysum', '>', 0)->orWhere('subq3.qtysum', '>', 0);
                }
            )
            //->whereRaw('(ifnull(subq2.qtysum,0)+ifnull(subq1.qtysum,0)-ifnull(subq3.qtysum,0))!=0')

            ->groupBy('pr_detail_int');

        $un_list = [];
        $un_list['users'] = User::role('supervisor')->select('name')->orderBy('name')->get()->pluck('name');
        $un_list['locs'] = Location::select('name')->orderBy('name')->get()->pluck('name');
        $un_list['sgroups'] = Pgroup::distinct()->pluck('sgroup', 'sgroup')->toArray();
        $un_list['gnames'] = Pgroup::distinct()->pluck('name', 'name')->toArray();

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('pr_detail_int')
            ->allowedSorts(array_merge(array_diff(array_keys($formInfo), []), array_keys($formInfoMulti), []))
            ->allowedFilters(array_merge(
                $filter_array,
                [
                    AllowedFilter::exact('pur_incharge')->ignore($un_list['users']),
                    AllowedFilter::exact('pur_loc')->ignore($un_list['locs']),
                    AllowedFilter::exact('stock_date')->ignore([!null, null]),
                    AllowedFilter::exact('sgroup', 'pgroups.sgroup'),
                    AllowedFilter::exact('group_name', 'pgroups.name'),
                    $globalSearch
                ]
            ))
            ->paginate($perPage)
            ->withQueryString();



        if (\Auth::user()->can('stocks_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }
        if (\Auth::user()->can('stocks_transfer')) {
            $this->resourceNeo['bulkActions']['transfer'] = [];
        }
        $this->resourceNeo['extraLinks'] = [];
        $this->resourceNeo['showTotal'] = true;
        $this->resourceNeo['showall'] = true;

        return Inertia::render('Admin/StocksView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo, $formInfoMulti, $un_list) {
            $table->withGlobalSearch();
            $arrKey = array_diff(array_keys($formInfo), []);
            foreach ($arrKey as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false, extra: ['type' => $formInfo[$key]['type'] ?? '', 'options' => [], 'align' => $formInfo[$key]['align'] ?? 'left', 'showTotal' => $formInfo[$key]['showTotal'] ?? false]);
            }
            foreach (array_keys($formInfoMulti) as $key) {
                $table->column($key, $formInfoMulti[$key]['label'], searchable: $formInfoMulti[$key]['searchable'] ?? false, sortable: $formInfoMulti[$key]['sortable'] ?? false, hidden: $formInfoMulti[$key]['hidden'] ?? false, extra: ['align' => $formInfoMulti[$key]['align'] ?? 'left', 'showTotal' => $formInfoMulti[$key]['showTotal'] ?? false]);
            }

            foreach ($un_list['users'] as  $opt) {
                $opt && $fresult2[$opt] = $opt;
            }

            $fresult4 = [];
            foreach ($un_list['locs'] as  $opt) {
                $opt && $fresult4[$opt] = $opt;
            }
            if (\Auth::user()->can('all') || \Auth::user()->can('stocks_list_for_all')) {
                $table->selectFilter(key: 'pur_incharge', label: 'Incharge', options: $fresult2, noFilterOptionLabel: 'All');
            }
            $table
                ->selectFilter(key: 'pur_loc', label: 'Location', options: $fresult4, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'group_name', label: 'Prod Group', options: $un_list['gnames'], noFilterOptionLabel: 'All')
                ->selectFilter(key: 'sgroup', label: 'Sub Group', options: $un_list['sgroups'], noFilterOptionLabel: 'All')
                ->dateFilter(key: 'stock_date', label: 'Stock As Of Date')
                ->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }
    public function locationIncharge()
    {
        $formInfo =
            [

                'pr_detail_int' => ['label' => 'Internal Product Name', 'searchable' => true, 'sortable' => true],
                'location' => ['label' => 'Location', 'sortable' => true],
                'incharge' => ['label' => 'Incharge', 'sortable' => true],

                'qty_sum_op' => ['label' => 'Qty In',  'align' => 'right', 'showTotal' => true],



                'qty_sum_out' => ['label' => 'Qty Out',  'align' => 'right', 'showTotal' => true],

                'balsum' => ['label' => 'Qty Bal', 'sortable' => true, 'align' => 'right', 'showTotal' => true],

                'pr_int_unit' => ['label' => 'Qty Unit',],

                'qty_alt_sum_op' => ['label' => 'Qty Alt In',  'align' => 'right', 'showTotal' => true],

                'qty_alt_sum_out' => ['label' => 'Qty Alt Out',  'align' => 'right', 'showTotal' => true],

                'balsumalt' => ['label' => 'Qty Bal Alt',   'align' => 'right', 'showTotal' => true],

                'pr_int_unit_alt' => ['label' => 'Qty Unit Alt',],
                'groupinfo_name' => ['label' => 'Prod Group', 'sortable' => true],
                'groupinfo_sname' => ['label' => 'Sub Group', 'sortable' => true],


            ];
        $formInfoMulti = [];
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($formInfo, $formInfoMulti) {
            $query->where(function ($query) use ($value, $formInfo, $formInfoMulti) {
                Collection::wrap($value)->each(function ($value) use ($query, $formInfo, $formInfoMulti) {
                    $query->orWhere('pr_detail_int', 'LIKE', "%{$value}%");
                });
            });
        });
        $perPage = request()->query('perPage') ?? 10;
        $filter_array = [];
        foreach (array_merge(array_diff(array_keys($formInfo), []), array_keys($formInfoMulti)) as $fvalue) {
            $filter_array[] = AllowedFilter::exact($fvalue);
        }

        $inc_u = request()->query('filter');

        $outSubQuery = Outward::select('out_product', 'out_loc', 'out_incharge')
            ->selectRaw('ifnull(sum(out_qty),0) as qtysum , ifnull(sum(out_qty_alt),0) as qtysumalt')->groupBy('out_product')->groupBy('out_loc')->groupBy('out_incharge');
        if (\Auth::user()->can('all') || \Auth::user()->can('stocks_list_for_all')) {
            if (isset($inc_u['pur_incharge'])) {
                $outSubQuery->where('out_incharge', $inc_u['pur_incharge']);
            }
        } else {
            $outSubQuery->where('out_incharge', \Auth::user()->name);
        }

        if (isset($inc_u['pur_loc'])) {
            $outSubQuery->where('out_loc', $inc_u['pur_loc']);
        }

        if (isset($inc_u['stock_date'])) {
            $outSubQuery->where('out_date', '<=', $inc_u['stock_date']);
        }

        //$outSubQuery->inFinancialYear();

        $inSubQuery = Purchase::select('pur_pr_detail_int', 'pur_loc', 'pur_incharge', 'subq1.qtysum as outqtysum', 'subq1.qtysumalt as outqtysumalt')
            ->selectRaw('ifnull(sum(pur_qty_int),0) as qtysum , ifnull(sum(pur_qty_int_alt),0) as qtysumalt')
            ->leftJoinSub($outSubQuery, 'subq1', function ($join) {
                $join->on('purchases.pur_pr_detail_int', '=', 'subq1.out_product')
                    ->whereRaw('purchases.pur_loc=subq1.out_loc')
                    ->whereRaw('purchases.pur_incharge=subq1.out_incharge');
            })
            ->groupBy('pur_pr_detail_int')->groupBy('pur_loc')->groupBy('pur_incharge')->orderBy('pur_pr_detail_int')->orderBy('pur_loc')->orderBy('pur_incharge');

        if (\Auth::user()->can('all') || \Auth::user()->can('stocks_list_for_all')) {
            if (isset($inc_u['pur_incharge'])) {
                $inSubQuery->where('pur_incharge', $inc_u['pur_incharge']);
            }
        } else {
            $inSubQuery->where('pur_incharge', \Auth::user()->name);
        }

        if (isset($inc_u['pur_loc'])) {
            $inSubQuery->where('pur_loc', $inc_u['pur_loc']);
        }


        if (isset($inc_u['stock_date'])) {
            $inSubQuery->where('pur_date', '<=', $inc_u['stock_date']);
        }

        //$inSubQuery->inFinancialYear();


        $query = Product::select('products.pr_detail_int', 'insubq1.pur_loc as location', 'insubq1.pur_incharge as incharge')
            ->selectRaw('MAX(products.id) as id')
            ->selectRaw('MAX(pr_int_unit_alt) as pr_int_unit_alt')
            ->selectRaw('MAX(pr_int_unit) as pr_int_unit')
            ->selectRaw('MAX(pgroups.name) as groupinfo_name')
            ->selectRaw('MAX(pgroups.sgroup) as groupinfo_sname')
            ->selectRaw('ifnull(MAX(insubq1.qtysum),0) as qty_sum_op , ifnull(MAX(insubq1.outqtysum),0) as qty_sum_out , ifnull(MAX(insubq1.qtysumalt),0) as qty_alt_sum_op, ifnull(MAX(insubq1.outqtysumalt),0) as qty_alt_sum_out')

            ->selectRaw('ifnull(MAX(insubq1.qtysum),0)-ifnull(MAX(insubq1.outqtysum),0) as balsum, ifnull(MAX(insubq1.qtysumalt),0)-ifnull(MAX(insubq1.outqtysumalt),0) as balsumalt')

            ->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo', 'left')
            ->leftJoinSub($inSubQuery, 'insubq1', function ($join) {
                $join->on('products.pr_detail_int', '=', 'insubq1.pur_pr_detail_int');
            })
            ->groupBy('pr_detail_int', 'location', 'incharge')
            ->havingRaw('(ifnull(MAX(insubq1.qtysum),0)-ifnull(MAX(insubq1.outqtysum),0))!=0');
        $un_list = [];
        $un_list['users'] = User::role('supervisor')->select('name')->orderBy('name')->get()->pluck('name');
        $un_list['locs'] = Location::select('name')->orderBy('name')->get()->pluck('name');
        $un_list['sgroups'] = Pgroup::distinct()->pluck('sgroup', 'sgroup')->toArray();
        $un_list['gnames'] = Pgroup::distinct()->pluck('name', 'name')->toArray();

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('pr_detail_int')
            ->allowedSorts(array_merge(array_diff(array_keys($formInfo), []), array_keys($formInfoMulti), []))
            ->allowedFilters(array_merge($filter_array, [
                AllowedFilter::exact('pur_incharge')->ignore($un_list['users']),
                AllowedFilter::exact('pur_loc')->ignore($un_list['locs']),
                AllowedFilter::exact('stock_date')->ignore([!null, null]),
                AllowedFilter::exact('sgroup', 'pgroups.sgroup'),
                AllowedFilter::exact('group_name', 'pgroups.name'),
                $globalSearch
            ]))
            ->paginate($perPage)
            ->withQueryString();



        if (\Auth::user()->can('stocks_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }
        if (\Auth::user()->can('stocks_transfer')) {
            $this->resourceNeo['bulkActions']['transfer'] = [];
        }
        $this->resourceNeo['extraLinks'] = [];
        $this->resourceNeo['showTotal'] = true;
        $this->resourceNeo['showall'] = true;

        $this->resourceNeo['resourceTitle'] = 'Stocks : Location and Incharge wise';

        return Inertia::render('Admin/IndexView', ['resourceData' => $resourceData, 'resourceNeo' => $this->resourceNeo])->table(function (InertiaTable $table) use ($formInfo, $formInfoMulti, $un_list) {
            $table->withGlobalSearch();
            $arrKey = array_diff(array_keys($formInfo), []);
            foreach ($arrKey as $key) {
                $table->column($key, $formInfo[$key]['label'], searchable: $formInfo[$key]['searchable'] ?? false, sortable: $formInfo[$key]['sortable'] ?? false, hidden: $formInfo[$key]['hidden'] ?? false, extra: ['type' => $formInfo[$key]['type'] ?? '', 'options' => [], 'align' => $formInfo[$key]['align'] ?? 'left', 'showTotal' => $formInfo[$key]['showTotal'] ?? false]);
            }
            foreach (array_keys($formInfoMulti) as $key) {
                $table->column($key, $formInfoMulti[$key]['label'], searchable: $formInfoMulti[$key]['searchable'] ?? false, sortable: $formInfoMulti[$key]['sortable'] ?? false, hidden: $formInfoMulti[$key]['hidden'] ?? false, extra: ['align' => $formInfoMulti[$key]['align'] ?? 'left', 'showTotal' => $formInfoMulti[$key]['showTotal'] ?? false]);
            }

            foreach ($un_list['users'] as  $opt) {
                $opt && $fresult2[$opt] = $opt;
            }

            $fresult4 = [];
            foreach ($un_list['locs'] as  $opt) {
                $opt && $fresult4[$opt] = $opt;
            }

            if (\Auth::user()->can('all') || \Auth::user()->can('stocks_list_for_all')) {
                $table->selectFilter(key: 'pur_incharge', label: 'Incharge', options: $fresult2, noFilterOptionLabel: 'All');
            }
            $table
                ->selectFilter(key: 'pur_loc', label: 'Location', options: $fresult4, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'group_name', label: 'Prod Group', options: $un_list['gnames'], noFilterOptionLabel: 'All')
                ->selectFilter(key: 'sgroup', label: 'Sub Group', options: $un_list['sgroups'], noFilterOptionLabel: 'All')
                ->dateFilter(key: 'stock_date', label: 'Stock As Of Date')
                ->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }
    public function StockDetail(Request $request)
    {
        $subq1 = Purchase::select('*')
        //->inFinancialYear()
        ->where('pur_pr_detail_int', $request->name)->get();
        $subq3 = Outward::select('*')
        //->inFinancialYear()
        ->where('out_product', $request->name)->get();

        // Merge the collections
        $merged = $subq1->merge($subq3);

        // Sort by created_at
        $sorted = $merged->sortByDesc('created_at');

        // If you need the sorted result as a fresh collection
        $sorted = $sorted->values();
        return $sorted;
    }
    public function transferStock(Request $request)
    {
        $request->validate([
            'superviser' => 'required',
            'location' => 'required',
            'stocks' => 'required|array',
        ]);

        $subq1 = Purchase::select('pur_pr_detail_int')->selectRaw('ifnull(sum(pur_qty_int),0) as qtysum , ifnull(sum(pur_qty_int_alt),0) as qtysumalt, ifnull(sum(pur_amnt),0) as amountsum')->where('entry_type', 0)->groupBy('pur_pr_detail_int');

        $subq2 = Purchase::select('pur_pr_detail_int')->selectRaw('ifnull(sum(pur_qty_int),0) as qtysum , ifnull(sum(pur_qty_int_alt),0) as qtysumalt, ifnull(sum(pur_amnt),0) as amountsum')->where('entry_type', 1)->groupBy('pur_pr_detail_int');

        $subq3 = Outward::select('out_product')->selectRaw('ifnull(sum(out_qty),0) as qtysum , ifnull(sum(out_qty_alt),0) as qtysumalt')->groupBy('out_product');


        //$subq1->inFinancialYear();
        //$subq2->inFinancialYear();
        //$subq3->inFinancialYear();

        $subq1->where('pur_incharge', $request->superviser_from);
        $subq2->where('pur_incharge', $request->superviser_from);
        $subq3->where('out_incharge', $request->superviser_from);

        $subq1->where('pur_loc', $request->location_from);
        $subq2->where('pur_loc', $request->location_from);
        $subq3->where('out_loc', $request->location_from);



        $query = Product::select('products.pr_detail_int')
            ->selectRaw('MAX(products.id) as id')
            ->selectRaw('MAX(products.pr_int_unit) as pr_int_unit')
            ->selectRaw('MAX(products.pr_int_unit_alt) as pr_int_unit_alt')
            ->selectRaw('MAX(pgroups.name) as groupinfo_name')
            ->selectRaw('ifnull(MAX(subq2.qtysum),0) as qty_sum_op , ifnull(MAX(subq1.qtysum),0) as qty_sum_pur , ifnull(MAX(subq3.qtysum),0) as qty_sum_out , ifnull(MAX(subq2.qtysumalt),0) as qty_alt_sum_op, ifnull(MAX(subq1.qtysumalt),0) as qty_alt_sum_pur, ifnull(MAX(subq3.qtysumalt),0) as qty_alt_sum_out')

            ->selectRaw('ifnull(MAX(subq2.qtysum),0)+ifnull(MAX(subq1.qtysum),0)-ifnull(MAX(subq3.qtysum),0) as balsum, ifnull(MAX(subq2.qtysumalt),0)+ifnull(MAX(subq1.qtysumalt),0)-ifnull(MAX(subq3.qtysumalt),0) as balsumalt')

            ->selectRaw('ROUND((ifnull(MAX(subq1.amountsum),0)+ifnull(MAX(subq2.amountsum),0))/(ifnull(MAX(subq1.qtysum),0)+if(ifnull(MAX(subq2.amountsum),0)>0,ifnull(MAX(subq2.qtysum),0),0)),3) as stock_unit_price')

            ->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo', 'left')
            ->leftJoinSub($subq1, 'subq1', function ($join) {
                $join->on('products.pr_detail_int', '=', 'subq1.pur_pr_detail_int');
            })
            ->leftJoinSub($subq2, 'subq2', function ($join) {
                $join->on('products.pr_detail_int', '=', 'subq2.pur_pr_detail_int');
            })
            ->leftJoinSub($subq3, 'subq3', function ($join) {
                $join->on('products.pr_detail_int', '=', 'subq3.out_product');
            })
            ->whereIn('products.id', $request->stocks)


            ->groupBy('pr_detail_int')->get();

        foreach ($query as $key => $perm) {
            // If 5 or fewer items selected, use provided quantities, otherwise transfer full amount
            $transferQty = count($request->stocks) <= 2
                ? floatval($request->quantities[$perm['id']] ?? 0)
                : $perm['balsum'];

            // Skip if no quantity to transfer
            if ($transferQty <= 0 || $transferQty > $perm['balsum']) {
                continue;
            }

            // Calculate alt quantity proportionally
            $altQtyRatio = $perm['balsumalt'] / $perm['balsum'];
            $transferAltQty = $transferQty * $altQtyRatio;

            // Create outward entry...
            $savedOut = [
                'out_date' => date('Y-m-d'),
                'out_incharge' => $request->superviser_from,
                'out_loc' => $request->location_from,
                'out_product_group' => $perm['groupinfo_name'],
                'out_product' => $perm['pr_detail_int'],
                'out_qty' => $transferQty,
                'out_qty_unit' => $perm['pr_int_unit'],
                'out_qty_alt' => $transferAltQty,
                'out_qty_unit_alt' => $perm['pr_int_unit_alt'],
                'out_remark' => 'Stock Transfer from ' . $request->superviser_from . '-' . $request->location_from,
                'out_product_id' => $perm['id']
            ];

            Outward::create($savedOut);

            // Create purchase entry for new location...
            $savedArray = [
                'pur_date' => date('Y-m-d'),
                'pur_inv' => 'Stock Transfer to ' . $request->superviser . '-' . $request->location,
                'pur_pr_detail_int' => $perm['pr_detail_int'],
                'pur_incharge' => $request->superviser,
                'pur_qty_int' => $transferQty,
                'pur_unint_int' => $perm['pr_int_unit'],
                'pur_qty_int_alt' => $transferAltQty,
                'pur_unint_int_alt' => $perm['pr_int_unit_alt'],
                'pur_loc' => $request->location,
                'pur_pr_id' => $perm['id'],
                'entry_type' => 1,
                'pur_rate_int' => $perm['stock_unit_price'] ?? 0,
                'pur_amnt' => $transferQty * ($perm['stock_unit_price'] ?? 0)
            ];

            Purchase::create($savedArray);
        }

        \ActivityLog::add(['action' => 'added', 'module' => $this->resourceNeo['resourceName'], 'data_key' => 'Partial Stock Transfer']);

        return redirect()->back()->with([
            'message' => $this->resourceNeo['resourceTitle'] . ' Stock Transferred Successfully !!',
            'msg_type' => 'info'
        ]);
    }

    /**
     * Display stock levels and thresholds.
     */
    public function stockLevels()
    {
        $formInfo = [
            'pr_detail_int' => ['label' => 'Internal Product Name', 'searchable' => false, 'sortable' => true],
            'balsum' => ['label' => 'Current Stock', 'sortable' => true, 'align' => 'right', 'showTotal' => true],
            'threshold_qty' => ['label' => 'Threshold Level', 'sortable' => true, 'align' => 'right'],
            'difference' => ['label' => 'Difference', 'sortable' => true, 'align' => 'right'],
            'pr_int_unit' => ['label' => 'Unit'],
            'status' => ['label' => 'Status']
        ];

        $perPage = request()->query('perPage') ?? 10;

        // Get current stock levels using existing query logic
        $subq1 = Purchase::select('pur_pr_detail_int')
            ->selectRaw('ifnull(sum(pur_qty_int),0) as qtysum')
            ->groupBy('pur_pr_detail_int');
            //->inFinancialYear();

        $subq3 = Outward::select('out_product')
            ->selectRaw('ifnull(sum(out_qty),0) as qtysum')
            ->groupBy('out_product');
            //->inFinancialYear();

        $query = Product::select('products.pr_detail_int')
            ->selectRaw('MAX(products.id) as id')
            ->selectRaw('MAX(pr_int_unit) as pr_int_unit')
            ->selectRaw('ifnull(MAX(subq1.qtysum),0)-ifnull(MAX(subq3.qtysum),0) as balsum')
            ->selectRaw('COALESCE(MAX(st.threshold_qty), 0) as threshold_qty')
            ->selectRaw('(ifnull(MAX(subq1.qtysum),0)-ifnull(MAX(subq3.qtysum),0) - COALESCE(MAX(st.threshold_qty), 0)) as difference')
            ->selectRaw("CASE 
                WHEN (ifnull(MAX(subq1.qtysum),0)-ifnull(MAX(subq3.qtysum),0)) <= COALESCE(MAX(st.threshold_qty), 0) 
                THEN 'Below Threshold' 
                ELSE 'Normal' 
                END as status")
            ->leftJoin('stock_thresholds as st', 'products.pr_detail_int', '=', 'st.pr_detail_int')
            ->leftJoinSub($subq1, 'subq1', function ($join) {
                $join->on('products.pr_detail_int', '=', 'subq1.pur_pr_detail_int');
            })
            ->leftJoinSub($subq3, 'subq3', function ($join) {
                $join->on('products.pr_detail_int', '=', 'subq3.out_product');
            })
            ->leftJoin('pgroups', 'pgroups.id', 'products.groupinfo', 'left')
            ->groupBy('pr_detail_int');

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query->orWhere('products.pr_detail_int', 'LIKE', "%{$value}%");
                });
            });
        });

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('pr_detail_int')
            ->allowedSorts(['pr_detail_int', 'balsum', 'threshold_qty', 'difference'])
            ->allowedFilters([$globalSearch, AllowedFilter::exact('sgroup', 'pgroups.sgroup'), AllowedFilter::exact('group_name', 'pgroups.name')])
            ->paginate($perPage)
            ->withQueryString();

        $this->resourceNeo['resourceTitle'] = 'Stock Threshold Levels';
        $this->resourceNeo['showTotal'] = true;

        if (\Auth::user()->can('stocks_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        $sgroups = Pgroup::distinct()->pluck('sgroup', 'sgroup')->toArray();
        $gnames = Pgroup::distinct()->pluck('name', 'name')->toArray();

        return Inertia::render('Admin/StockLevelsView', [
            'resourceData' => $resourceData,
            'resourceNeo' => $this->resourceNeo
        ])->table(function (InertiaTable $table) use ($formInfo, $sgroups, $gnames) {
            $table->withGlobalSearch()
                ->selectFilter(key: 'group_name', label: 'Prod Group', options: $gnames, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'sgroup', label: 'Sub Group', options: $sgroups, noFilterOptionLabel: 'All');
            foreach ($formInfo as $key => $config) {
                $table->column(
                    key: $key,
                    label: $config['label'],
                    searchable: $config['searchable'] ?? false,
                    sortable: $config['sortable'] ?? false,
                    extra: [
                        'align' => $config['align'] ?? 'left',
                        'showTotal' => $config['showTotal'] ?? false
                    ]
                );
            }
            $table->column(label: 'Actions');
            $table->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }

    /**
     * Update threshold quantity for a product
     */
    public function updateThreshold(Request $request)
    {
        $request->validate([
            'pr_detail_int' => 'required|exists:products,pr_detail_int',
            'threshold_qty' => 'required|numeric|min:0',
        ]);

        StockThreshold::updateOrCreate(
            ['pr_detail_int' => $request->pr_detail_int],
            ['threshold_qty' => $request->threshold_qty]
        );

        \ActivityLog::add([
            'action' => 'updated',
            'module' => 'stock_threshold',
            'data_key' => $request->pr_detail_int
        ]);

        return redirect()->back()->with([
            'message' => 'Stock threshold updated successfully',
            'msg_type' => 'info'
        ]);
    }
}
