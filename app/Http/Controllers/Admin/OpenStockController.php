<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsumableInternalName;
use App\Models\OpenStockBalance;
use App\Models\OpenStockTransaction;
use App\Models\Purchase;
use App\Services\OpenStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OpenStockController extends Controller
{
    protected $resourceNeo = [
        'resourceName' => 'openStock',
        'resourceTitle' => 'Open Stock',
        'iconPath' => 'M4,4H20V6H4V4M4,8H20V16H4V8M4,18H20V20H4V18Z',
        'actions' => [ 'r'],
    ];

    public function __construct()
    {
        $this->middleware('can:openStock_list', ['only' => ['index', 'detail']]);
        $this->middleware('can:openStock_adjust', ['only' => ['create', 'store']]);
    }

    public function index()
    {
        $formInfo = [
            'internal_name' => ['label' => 'Internal Name', 'searchable' => true, 'sortable' => true],
            'location' => ['label' => 'Location', 'searchable' => true, 'sortable' => true],
            'incharge' => ['label' => 'Incharge', 'searchable' => true, 'sortable' => true],
            'open_stock_unit' => ['label' => 'Open Stock Unit', 'sortable' => true],
            'qty' => ['label' => 'Qty', 'sortable' => true, 'align' => 'right', 'showTotal' => true],
            'current_unit_price' => ['label' => 'Current Unit Price', 'sortable' => true, 'align' => 'right'],
            'stock_value' => ['label' => 'Stock Value', 'sortable' => true, 'align' => 'right', 'showTotal' => true],
        ];

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query->orWhere('open_stock_balances.internal_name', 'LIKE', "%{$value}%")
                        ->orWhere('open_stock_balances.location', 'LIKE', "%{$value}%")
                        ->orWhere('open_stock_balances.incharge', 'LIKE', "%{$value}%");
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;

        $latestConsumablePerName = ConsumableInternalName::selectRaw('MAX(id) as max_id, name')
            ->groupBy('name');

        $query = OpenStockBalance::select('open_stock_balances.*')
            ->selectRaw('IFNULL(cin.unitPrice, 0) as current_unit_price')
            ->selectRaw('ROUND(open_stock_balances.qty * IFNULL(cin.unitPrice, 0), 2) as stock_value')
            ->leftJoinSub($latestConsumablePerName, 'cin_latest', function ($join) {
                $join->on('cin_latest.name', '=', 'open_stock_balances.internal_name');
            })
            ->leftJoin('consumable_internal_names as cin', 'cin.id', '=', 'cin_latest.max_id');

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('internal_name')
            ->allowedSorts(['internal_name', 'location', 'incharge', 'open_stock_unit', 'qty', 'current_unit_price', 'stock_value'])
            ->allowedFilters([
                AllowedFilter::exact('internal_name'),
                AllowedFilter::exact('location'),
                AllowedFilter::exact('incharge'),
                AllowedFilter::exact('open_stock_unit'),
                $globalSearch,
            ])
            ->paginate($perPage)
            ->withQueryString();

        if (Auth::user()->can('openStock_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        if (Auth::user()->can('openStock_adjust')) {
            $this->resourceNeo['extraMainLinks'] = [
                [
                    'label' => 'Manual +/- Adjustment',
                    'link' => 'openStock.create',
                    'icon' => 'M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z',
                ],
            ];
        }
        $this->resourceNeo['showTotal'] = true;

        return Inertia::render('Admin/OpenStockView', [
            'resourceData' => $resourceData,
            'resourceNeo' => $this->resourceNeo,
        ])->table(function (InertiaTable $table) use ($formInfo) {
            $table->withGlobalSearch();
            foreach ($formInfo as $key => $value) {
                $table->column(
                    $key,
                    $value['label'],
                    searchable: $value['searchable'] ?? false,
                    sortable: $value['sortable'] ?? false,
                    extra: [
                        'align' => $value['align'] ?? 'left',
                        'showTotal' => $value['showTotal'] ?? false,
                    ]
                );
            }

            $table->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }

    public function create()
    {
        $internalNameQuery = Purchase::query()
            ->select('purchases.pur_pr_detail_int')
            ->leftJoin('products', 'products.id', '=', 'purchases.pur_pr_id')
            ->leftJoin('pgroups', 'pgroups.id', '=', 'products.groupinfo')
            ->where('pgroups.sgroup', 'Stock Item')
            ->whereNotNull('purchases.pur_pr_detail_int')
            ->where('purchases.pur_pr_detail_int', '!=', '')
            ->inFinancialYear()
            ->orderBy('purchases.pur_pr_detail_int');

        if (!(Auth::user()->can('all') || Auth::user()->can('outward_add_for_all'))) {
            $internalNameQuery->where('purchases.pur_incharge', Auth::user()->name);
        }

        $internalNames = $internalNameQuery->pluck('purchases.pur_pr_detail_int')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $resourceNeo = $this->resourceNeo;
        $resourceNeo['formInfo'] = [
            'txn_date' => ['label' => 'Date', 'vRule' => 'required|date', 'type' => 'datepicker', 'default' => date('Y-m-d')],
            'internal_name' => [
                'label' => 'Internal Name',
                'vRule' => 'required',
                'type' => 'select',
                'optionType' => 'array',
                'options' => $internalNames,
            ],
            'location' => [
                'label' => 'Location',
                'vRule' => 'required',
                'type' => 'select',
                'optionType' => 'array',
                'options' => [],
            ],
            'incharge' => [
                'label' => 'Incharge',
                'vRule' => 'required',
                'type' => 'select',
                'optionType' => 'array',
                'options' => [],
            ],
            'adjustment_type' => [
                'label' => 'Adjustment Type',
                'vRule' => 'required|in:plus,minus',
                'type' => 'select',
                'optionType' => 'array',
                'options' => ['plus', 'minus'],
                'default' => 'plus',
            ],
            'qty' => ['label' => 'Qty', 'vRule' => 'required|numeric|min:0.0001'],
            'remark' => ['label' => 'Remark', 'vRule' => 'nullable'],
        ];

        return Inertia::render('Admin/OpenStockAddEditView', compact('resourceNeo'));
    }

    public function detail(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:open_stock_balances,id',
        ]);

        $balance = OpenStockBalance::findOrFail($request->id);

        return OpenStockTransaction::where('internal_name', $balance->internal_name)
            ->where('location', $balance->location)
            ->where('incharge', $balance->incharge)
            ->orderByDesc('txn_date')
            ->orderByDesc('id')
            ->get();
    }

    public function store(Request $request)
    {
        $rules = [
            'txn_date' => 'required|date',
            'internal_name' => 'required|string',
            'location' => 'required|string',
            'incharge' => 'required|string',
            'adjustment_type' => 'required|in:plus,minus',
            'qty' => 'required|numeric|min:0.0001',
            'remark' => 'nullable|string',
        ];

        $validated = $request->validate($rules);
        $validated['txn_date'] = date('Y-m-d', strtotime($validated['txn_date']));

        $service = new OpenStockService();
        $service->adjustStock($validated);

        \ActivityLog::add([
            'action' => 'added',
            'module' => $this->resourceNeo['resourceName'],
            'data_key' => $validated['internal_name'],
        ]);

        return redirect()->route('openStock.index')->with([
            'message' => 'Open Stock adjusted successfully !!',
            'msg_type' => 'success',
        ]);
    }
}
