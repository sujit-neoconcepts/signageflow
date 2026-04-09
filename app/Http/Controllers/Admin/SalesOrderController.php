<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CostSheet;
use App\Models\SalesOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SalesOrderController extends Controller
{
    protected $resourceNeo = [
        'resourceName' => 'salesOrder',
        'resourceTitle' => 'Sales Order',
        'iconPath' => 'M2,4H22V6H2V4M2,8H22V16H2V8M2,18H22V20H2V18M6,10H8V14H6V10M10,10H18V12H10V10M10,12.5H16V14.5H10V12.5Z',
        'actions' => ['c', 'r', 'u', 'd'],
    ];

    public function getResourceNeo(): array
    {
        return $this->resourceNeo;
    }

    public function __construct()
    {
        $this->middleware('can:salesOrder_list', ['only' => ['index', 'show', 'detail', 'print']]);
        $this->middleware('can:salesOrder_create', ['only' => ['create', 'store']]);
        $this->middleware('can:salesOrder_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:salesOrder_delete', ['only' => ['destroy', 'bulkDestroy']]);
    }

    public function index()
    {
        $formInfo = [
            'order_no' => ['label' => 'Order No', 'searchable' => true, 'sortable' => true],
            'order_date' => ['label' => 'Order Date', 'searchable' => true, 'sortable' => true, 'type' => 'datepicker'],
            'client_name' => ['label' => 'Client', 'searchable' => true, 'sortable' => true],
            'product_type' => ['label' => 'Product Type', 'searchable' => true, 'sortable' => true],
            'total_amount' => ['label' => 'Total Amount', 'sortable' => true, 'align' => 'right', 'showTotal' => true],
            'remark' => ['label' => 'Remark', 'searchable' => true, 'sortable' => true],
        ];

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query->orWhere('sales_orders.order_no', 'LIKE', "%{$value}%")
                        ->orWhere('sales_orders.product_type', 'LIKE', "%{$value}%")
                        ->orWhere('sales_orders.remark', 'LIKE', "%{$value}%")
                        ->orWhere('clients.cl_name', 'LIKE', "%{$value}%");
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;

        $query = SalesOrder::select('sales_orders.*', 'clients.cl_name as client_name')
            ->leftJoin('clients', 'clients.id', '=', 'sales_orders.client_id')
            ->inFinancialYear();

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('-id')
            ->allowedSorts(['id', 'order_no', 'order_date', 'client_name', 'product_type', 'total_amount', 'remark'])
            ->allowedFilters([
                AllowedFilter::exact('client_id'),
                AllowedFilter::exact('product_type'),
                AllowedFilter::scope('order_date_start'),
                AllowedFilter::scope('order_date_end'),
                $globalSearch,
            ])
            ->paginate($perPage)
            ->withQueryString();

        if (\Auth::user()->can('salesOrder_delete')) {
            $this->resourceNeo['bulkActions']['bulk_delete'] = [];
        }

        if (\Auth::user()->can('salesOrder_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        $this->resourceNeo['showTotal'] = true;

        return Inertia::render('Admin/SalesOrderIndexView', [
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
                        'type' => $value['type'] ?? '',
                        'align' => $value['align'] ?? 'left',
                        'showTotal' => $value['showTotal'] ?? false,
                    ]
                );
            }

            $table->column(label: 'Actions')
                ->dateFilter(key: 'order_date_start', label: 'Date From')
                ->dateFilter(key: 'order_date_end', label: 'Date To')
                ->selectFilter(key: 'product_type', label: 'Product Type', options: [
                    'signage' => 'Signage',
                    'cabinet' => 'Cabinet',
                    'letters' => 'Letters',
                ], noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50, 100, 10000]);
        });
    }

    public function detail(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:sales_orders,id',
        ]);

        $salesOrder = SalesOrder::with(['client:id,cl_name', 'items.costSheet:id,qty_unit'])
            ->findOrFail($request->id);

        $transportGst = round(((float) $salesOrder->transport_charge * (float) $salesOrder->gst_percent) / 100, 2);

        return response()->json([
            'id' => $salesOrder->id,
            'order_no' => $salesOrder->order_no,
            'order_date' => $salesOrder->order_date,
            'client' => $salesOrder->client?->cl_name,
            'product_type' => $salesOrder->product_type,
            'remark' => $salesOrder->remark,
            'transport_charge' => $salesOrder->transport_charge,
            'gst_percent' => $salesOrder->gst_percent,
            'items_taxable_total' => $salesOrder->items_taxable_total,
            'items_gst_total' => $salesOrder->items_gst_total,
            'transport_gst' => $transportGst,
            'total_amount' => $salesOrder->total_amount,
            'items' => $salesOrder->items->map(function ($item) {
                return [
                    'item_name' => $item->item_name,
                    'qty_mode' => $item->qty_mode,
                    'length' => $item->length,
                    'width' => $item->width,
                    'pieces' => $item->pieces,
                    'qty' => $item->qty,
                    'qty_unit' => $item->costSheet?->qty_unit,
                    'rate' => $item->rate,
                    'taxable_amount' => $item->taxable_amount,
                    'gst_percent' => $item->gst_percent,
                    'gst_amount' => $item->gst_amount,
                    'line_total' => $item->line_total,
                ];
            })->values(),
        ]);
    }

    public function show(SalesOrder $salesOrder)
    {
    }

    public function print(SalesOrder $salesOrder)
    {
        $salesOrder->load(['client:id,cl_name', 'items.costSheet:id,qty_unit']);

        $itemsTaxableTotal = (float) $salesOrder->items_taxable_total;
        $transportGst = round(((float) $salesOrder->transport_charge * (float) $salesOrder->gst_percent) / 100, 2);

        $pdf = Pdf::loadView('Admin.sales-order-invoice', [
            'salesOrder' => $salesOrder,
            'itemsTotal' => round($itemsTaxableTotal, 2),
            'transportGst' => $transportGst,
        ])->setPaper('a4', 'portrait');

        $safeOrderNo = str_replace(['/', '\\'], '-', (string) $salesOrder->order_no);
        return $pdf->stream('sales-order-' . $safeOrderNo . '.pdf');
    }

    public function create()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['mode'] = 'create';

        $clients = $this->getClients();
        $costSheetOptions = $this->getCostSheetOptions();

        return Inertia::render('Admin/SalesOrderAddEditView', compact('resourceNeo', 'clients', 'costSheetOptions'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $items = $this->normalizeItems($request->items ?? []);
        $transportCharge = round((float) ($validated['transport_charge'] ?? 0), 2);
        $orderGstPercent = round((float) ($validated['gst_percent'] ?? 18), 2);

        if (count($items) === 0) {
            return redirect()->back()->withErrors(['items' => 'Add at least one item.'])->withInput();
        }

        $orderDate = Carbon::parse($validated['order_date'])->format('Y-m-d');
        $maxAttempt = 5;

        for ($attempt = 1; $attempt <= $maxAttempt; $attempt++) {
            DB::beginTransaction();
            try {
                [$orderNo, $prefix, $sequence, $fyCode] = $this->nextOrderNumber($validated['product_type'], $orderDate);
                $lineResult = $this->buildLineRows($validated['product_type'], $items);

                $order = SalesOrder::create([
                    'order_no'      => $orderNo,
                    'order_prefix'  => $prefix,
                    'order_sequence' => $sequence,
                    'order_fy'      => $fyCode,
                    'order_date'    => $orderDate,
                    'client_id'     => $validated['client_id'],
                    'enquiry_id'    => $request->input('enquiry_id') ?: null,
                    'product_type'  => $validated['product_type'],
                    'remark'        => $validated['remark'] ?? null,
                    'total_amount'  => 0,
                ]);

                if (!empty($lineResult['rows'])) {
                    $order->items()->createMany($lineResult['rows']);
                }

                $transportGst = round(($transportCharge * $orderGstPercent) / 100, 2);
                $totalAmount = round(
                    $lineResult['items_taxable_total'] +
                    $lineResult['items_gst_total'] +
                    $transportCharge +
                    $transportGst,
                    2
                );

                $order->update([
                    'transport_charge'    => $transportCharge,
                    'gst_percent'         => $orderGstPercent,
                    'items_taxable_total' => $lineResult['items_taxable_total'],
                    'items_gst_total'     => $lineResult['items_gst_total'],
                    'total_amount'        => $totalAmount,
                ]);

                // Mark linked enquiry as pushed
                if ($order->enquiry_id) {
                    \App\Models\Enquiry::where('id', $order->enquiry_id)
                        ->update(['status' => \App\Models\Enquiry::STATUS_PUSHED]);
                }
                DB::commit();

                \ActivityLog::add([
                    'action' => 'added',
                    'module' => $this->resourceNeo['resourceName'],
                    'data_key' => $order->order_no,
                ]);

                return redirect()->route('salesOrder.index')->with([
                    'message' => 'Sales Order Created Successfully !!',
                    'msg_type' => 'success',
                ]);
            } catch (QueryException $e) {
                DB::rollBack();
                if ($this->isDuplicateKeyException($e) && $attempt < $maxAttempt) {
                    continue;
                }

                return redirect()->back()->withErrors(['salesOrder' => $e->getMessage()])->withInput();
            } catch (\Throwable $e) {
                DB::rollBack();
                return redirect()->back()->withErrors(['salesOrder' => $e->getMessage()])->withInput();
            }
        }

        return redirect()->back()->withErrors([
            'salesOrder' => 'Unable to create sales order. Please try again.',
        ])->withInput();
    }

    public function edit(SalesOrder $salesOrder)
    {
        $salesOrder->load(['items']);

        $formdata = [
            'id' => $salesOrder->id,
            'order_no' => $salesOrder->order_no,
            'order_date' => $salesOrder->order_date,
            'client_id' => $salesOrder->client_id,
            'product_type' => $salesOrder->product_type,
            'remark' => $salesOrder->remark,
            'transport_charge' => $salesOrder->transport_charge,
            'gst_percent' => $salesOrder->gst_percent,
            'items' => $salesOrder->items->map(function ($item) {
                return [
                    'cost_sheet_id' => $item->cost_sheet_id,
                    'item_name' => $item->item_name,
                    'qty_mode' => $item->qty_mode,
                    'length' => $item->length,
                    'width' => $item->width,
                    'pieces' => $item->pieces,
                    'qty' => $item->qty,
                    'rate' => $item->rate,
                    'gst_percent' => $item->gst_percent,
                ];
            })->values(),
        ];

        $resourceNeo = $this->resourceNeo;
        $resourceNeo['mode'] = 'edit';

        $clients = $this->getClients();
        $costSheetOptions = $this->getCostSheetOptions();

        return Inertia::render('Admin/SalesOrderAddEditView', compact('formdata', 'resourceNeo', 'clients', 'costSheetOptions'));
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        $validated = $this->validatePayload($request, $salesOrder->id);

        $items = $this->normalizeItems($request->items ?? []);
        $transportCharge = round((float) ($validated['transport_charge'] ?? 0), 2);
        $orderGstPercent = round((float) ($validated['gst_percent'] ?? 18), 2);

        if (count($items) === 0) {
            return redirect()->back()->withErrors(['items' => 'Add at least one item.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $lineResult = $this->buildLineRows($validated['product_type'], $items);

            $salesOrder->items()->delete();
            $salesOrder->update([
                'order_date' => Carbon::parse($validated['order_date'])->format('Y-m-d'),
                'client_id' => $validated['client_id'],
                'product_type' => $validated['product_type'],
                'remark' => $validated['remark'] ?? null,
                'transport_charge' => $transportCharge,
                'gst_percent' => $orderGstPercent,
                'items_taxable_total' => 0,
                'items_gst_total' => 0,
                'total_amount' => 0
            ]);

            if (!empty($lineResult['rows'])) {
                $salesOrder->items()->createMany($lineResult['rows']);
            }

            $transportGst = round(($transportCharge * $orderGstPercent) / 100, 2);

            $salesOrder->update([
                'items_taxable_total' => $lineResult['items_taxable_total'],
                'items_gst_total' => $lineResult['items_gst_total'],
                'total_amount' => round(
                    $lineResult['items_taxable_total'] +
                    $lineResult['items_gst_total'] +
                    $transportCharge +
                    $transportGst,
                    2
                ),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['salesOrder' => $e->getMessage()])->withInput();
        }

        \ActivityLog::add([
            'action' => 'updated',
            'module' => $this->resourceNeo['resourceName'],
            'data_key' => $salesOrder->order_no,
        ]);

        return redirect()->route('salesOrder.index')->with([
            'message' => 'Sales Order Updated Successfully !!',
            'msg_type' => 'success',
        ]);
    }

    public function destroy(SalesOrder $salesOrder)
    {
        $orderNo = $salesOrder->order_no;
        $enquiryId = $salesOrder->enquiry_id;
        $salesOrder->delete();

        // Revert enquiry status if no other sales orders remain linked
        if ($enquiryId) {
            $remaining = SalesOrder::where('enquiry_id', $enquiryId)->count();
            if ($remaining === 0) {
                \App\Models\Enquiry::where('id', $enquiryId)
                    ->update(['status' => \App\Models\Enquiry::STATUS_OPEN]);
            }
        }

        \ActivityLog::add([
            'action' => 'deleted',
            'module' => $this->resourceNeo['resourceName'],
            'data_key' => $orderNo,
        ]);

        return redirect()->back()->with(['message' => 'Sales Order Deleted !!', 'msg_type' => 'success']);
    }

    public function bulkDestroy()
    {
        $ids = request('ids', []);

        // Before deleting, collect enquiry IDs that might need reverting
        $enquiryIds = SalesOrder::whereIn('id', $ids)->whereNotNull('enquiry_id')
            ->pluck('enquiry_id')->unique()->values()->toArray();

        SalesOrder::whereIn('id', $ids)->delete();

        // Revert enquiry status for those that no longer have any linked sales orders
        foreach ($enquiryIds as $enqId) {
            $remaining = SalesOrder::where('enquiry_id', $enqId)->count();
            if ($remaining === 0) {
                \App\Models\Enquiry::where('id', $enqId)
                    ->update(['status' => \App\Models\Enquiry::STATUS_OPEN]);
            }
        }

        $uname = (count($ids) > 50) ? 'Many' : implode(',', $ids);
        \ActivityLog::add([
            'action' => 'deleted',
            'module' => $this->resourceNeo['resourceName'],
            'data_key' => $uname,
        ]);

        return redirect()->back()->with(['message' => 'Selected Sales Orders Deleted !!', 'msg_type' => 'success']);
    }

    protected function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'order_date' => 'required|date',
            'client_id' => 'required|exists:clients,id',
            'product_type' => 'required|in:signage,cabinet,letters',
            'remark' => 'nullable|string',
            'transport_charge' => 'nullable|numeric|min:0',
            'gst_percent' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.cost_sheet_id' => 'required_with:items|exists:cost_sheets,id',
            'items.*.qty' => 'nullable|numeric|min:0',
            'items.*.rate' => 'required_with:items|numeric|min:0',
            'items.*.gst_percent' => 'required_with:items|numeric|min:0',
            'items.*.length' => 'nullable|numeric|min:0',
            'items.*.width' => 'nullable|numeric|min:0',
            'items.*.pieces' => 'nullable|numeric|min:0',
        ]);
    }

    protected function normalizeItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $costSheetId = (int) ($item['cost_sheet_id'] ?? 0);
            $qty = (float) ($item['qty'] ?? 0);
            $rate = (float) ($item['rate'] ?? 0);
            $gstPercent = (float) ($item['gst_percent'] ?? 18);
            $length = array_key_exists('length', $item) ? (float) ($item['length'] ?? 0) : null;
            $width = array_key_exists('width', $item) ? (float) ($item['width'] ?? 0) : null;
            $pieces = array_key_exists('pieces', $item) ? (float) ($item['pieces'] ?? 0) : null;

            if ($costSheetId <= 0) {
                continue;
            }

            $result[] = [
                'cost_sheet_id' => $costSheetId,
                'qty' => round($qty, 4),
                'rate' => round($rate, 4),
                'gst_percent' => round($gstPercent, 2),
                'length' => $length !== null ? round($length, 4) : null,
                'width' => $width !== null ? round($width, 4) : null,
                'pieces' => $pieces !== null ? round($pieces, 4) : null,
            ];
        }

        return $result;
    }

    protected function buildLineRows(string $productType, array $items): array
    {
        if (empty($items)) {
            return ['rows' => [], 'items_taxable_total' => 0, 'items_gst_total' => 0];
        }

        $costSheetIds = array_values(array_unique(array_map(fn ($item) => (int) $item['cost_sheet_id'], $items)));
        $costSheets = CostSheet::whereIn('id', $costSheetIds)->get()->keyBy('id');

        $rows = [];
        $itemsTaxableTotal = 0;
        $itemsGstTotal = 0;

        foreach ($items as $item) {
            $costSheet = $costSheets->get((int) $item['cost_sheet_id']);
            if (!$costSheet) {
                throw new \RuntimeException('Selected cost sheet item not found.');
            }
            if ($costSheet->prod_type !== $productType) {
                throw new \RuntimeException('Selected item does not belong to selected product type.');
            }

            $normalizedUnit = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $costSheet->qty_unit)));
            $isDimensionQty = in_array($normalizedUnit, ['sqft', 'sqf', 'sqm'], true);
            $length = null;
            $width = null;
            $pieces = null;
            $qtyMode = 'direct';
            $qty = (float) $item['qty'];

            if ($isDimensionQty) {
                $length = (float) ($item['length'] ?? 0);
                $width = (float) ($item['width'] ?? 0);
                $pieces = (float) ($item['pieces'] ?? 0);
                if ($length <= 0 || $width <= 0 || $pieces <= 0) {
                    throw new \RuntimeException("Length, Width and Q are required for {$costSheet->name}.");
                }
                $qty = round($length * $width * $pieces, 4);
                if (in_array($normalizedUnit, ['sqft', 'sqf'], true)) {
                    $qty = round($qty / 144, 4);
                }
                $qtyMode = 'dimension';
            } else {
                if ($qty <= 0) {
                    throw new \RuntimeException("Quantity must be greater than zero for {$costSheet->name}.");
                }
            }

            $rate = (float) $item['rate'];
            $lineGstPercent = max(0, (float) ($item['gst_percent'] ?? 18));
            $taxableAmount = round($qty * $rate, 2);
            $gstAmount = round(($taxableAmount * $lineGstPercent) / 100, 2);
            $lineTotal = round($taxableAmount + $gstAmount, 2);

            $rows[] = [
                'cost_sheet_id' => $costSheet->id,
                'item_name' => $costSheet->name,
                'qty_mode' => $qtyMode,
                'length' => $length,
                'width' => $width,
                'pieces' => $pieces,
                'qty' => $qty,
                'rate' => $rate,
                'line_total' => $lineTotal,
                'taxable_amount' => $taxableAmount,
                'gst_percent' => $lineGstPercent,
                'gst_amount' => $gstAmount,
            ];
            $itemsTaxableTotal += $taxableAmount;
            $itemsGstTotal += $gstAmount;
        }

        return [
            'rows' => $rows,
            'items_taxable_total' => round($itemsTaxableTotal, 2),
            'items_gst_total' => round($itemsGstTotal, 2),
        ];
    }

    public function getClients(): Collection
    {
        return Client::select('id', 'cl_name')
            ->orderBy('cl_name')
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'label' => $client->cl_name,
                ];
            })
            ->values();
    }

    public function getCostSheetOptions(): array
    {
        return CostSheet::select('id', 'name', 'prod_type', 'qty_unit', 'rate')
            ->orderBy('prod_type')
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'label' => $item->name,
                    'name' => $item->name,
                    'prod_type' => $item->prod_type,
                    'qty_unit' => $item->qty_unit,
                    'rate' => (float) $item->rate,
                ];
            })
            ->values()
            ->toArray();
    }

    protected function nextOrderNumber(string $productType, string $orderDate): array
    {
        $prefix = $this->prefixForType($productType);
        $fyCode = $this->fyCodeFromDate($orderDate);

        $maxSequence = (int) SalesOrder::where('order_prefix', $prefix)
            ->where('order_fy', $fyCode)
            ->lockForUpdate()
            ->max('order_sequence');

        $nextSequence = $maxSequence + 1;
        $orderNo = $prefix . str_pad((string) $nextSequence, 2, '0', STR_PAD_LEFT) . '/' . $fyCode;

        return [$orderNo, $prefix, $nextSequence, $fyCode];
    }

    protected function prefixForType(string $productType): string
    {
        return match ($productType) {
            'signage' => 'S',
            'cabinet' => 'C',
            'letters' => 'L',
            default => throw new \InvalidArgumentException('Invalid product type.'),
        };
    }

    protected function fyCodeFromDate(string $orderDate): string
    {
        $date = Carbon::parse($orderDate);
        $startYear = ((int) $date->format('n') >= 4) ? (int) $date->format('Y') : ((int) $date->format('Y') - 1);
        $nextYear = $startYear + 1;

        return substr((string) $startYear, -2) . substr((string) $nextYear, -2);
    }

    protected function isDuplicateKeyException(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }
}
