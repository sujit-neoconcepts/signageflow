<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CostSheet;
use App\Models\Enquiry;
use App\Models\EnquiryFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EnquiryController extends Controller
{
    protected $resourceNeo = [
        'resourceName' => 'enquiry',
        'resourceTitle' => 'Enquiry',
        'iconPath' => 'M9,6V18H15V6H9M11,8H13V10H11V8M11,12H13V14H11V12M11,16H13V18H11V16H11M16,6H18V18H16V6M20,6V18H22V6H20Z',
        'actions' => ['c', 'r', 'u', 'd'],
    ];

    public function __construct()
    {
        $this->middleware('can:enquiry_list', ['only' => ['index', 'show', 'detail', 'print']]);
        $this->middleware('can:enquiry_create', ['only' => ['create', 'store', 'pushToSalesOrder']]);
        $this->middleware('can:enquiry_edit', ['only' => ['edit', 'update']]);
        $this->middleware('can:enquiry_delete', ['only' => ['destroy', 'bulkDestroy']]);
    }

    public function index()
    {
        $formInfo = [
            'enquiry_no'   => ['label' => 'Enquiry No',   'searchable' => true, 'sortable' => true],
            'enquiry_date' => ['label' => 'Enquiry Date', 'searchable' => true, 'sortable' => true, 'type' => 'datepicker'],
            'client_name'  => ['label' => 'Client',       'searchable' => true, 'sortable' => true],
            'product_type' => ['label' => 'Product Type', 'searchable' => true, 'sortable' => true],
            'status'       => ['label' => 'Status',       'searchable' => true, 'sortable' => true],
            'remark'       => ['label' => 'Remark',       'searchable' => true, 'sortable' => true],
        ];

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query->orWhere('enquiries.enquiry_no', 'LIKE', "%{$value}%")
                        ->orWhere('enquiries.product_type', 'LIKE', "%{$value}%")
                        ->orWhere('enquiries.remark', 'LIKE', "%{$value}%")
                        ->orWhere('clients.cl_name', 'LIKE', "%{$value}%");
                });
            });
        });

        $perPage = request()->query('perPage') ?? 10;

        $query = Enquiry::select('enquiries.*', 'clients.cl_name as client_name')
            ->leftJoin('clients', 'clients.id', '=', 'enquiries.client_id');
            //->inFinancialYear();

        $resourceData = QueryBuilder::for($query)
            ->defaultSort('-id')
            ->allowedSorts(['id', 'enquiry_no', 'enquiry_date', 'client_name', 'product_type', 'total_amount', 'remark'])
            ->allowedFilters([
                AllowedFilter::exact('client_id'),
                AllowedFilter::exact('product_type'),
                AllowedFilter::scope('order_date_start'),
                AllowedFilter::scope('order_date_end'),
                $globalSearch,
            ])
            ->paginate($perPage)
            ->withQueryString();

        if (\Auth::user()->can('enquiry_delete')) {
            $this->resourceNeo['bulkActions']['bulk_delete'] = [];
        }

        if (\Auth::user()->can('enquiry_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        $this->resourceNeo['showTotal'] = true;

        return Inertia::render('Admin/EnquiryIndexView', [
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
            'id' => 'required|integer|exists:enquiries,id',
        ]);

        $enquiry = Enquiry::with(['client:id,cl_name', 'items.costSheet:id,qty_unit', 'customItems', 'files'])
            ->findOrFail($request->id);

        $transportGst = round(((float) $enquiry->transport_charge * (float) $enquiry->gst_percent) / 100, 2);

        return response()->json([
            'id' => $enquiry->id,
            'enquiry_no' => $enquiry->enquiry_no,
            'enquiry_date' => $enquiry->enquiry_date,
            'client' => $enquiry->client?->cl_name,
            'product_type' => $enquiry->product_type,
            'remark' => $enquiry->remark,
            'items' => $enquiry->items->map(function ($item) {
                return [
                    'item_name' => $item->item_name,
                    'qty_mode' => $item->qty_mode,
                    'length' => $item->length,
                    'width' => $item->width,
                    'pieces' => $item->pieces,
                    'qty' => $item->qty,
                    'qty_unit' => $item->costSheet?->qty_unit,
                ];
            })->values(),
            'custom_items' => $enquiry->customItems->map(function ($ci) {
                return [
                    'item_name' => $ci->item_name,
                    'qty' => $ci->qty,
                ];
            })->values(),
            'files' => $enquiry->files->map(function ($f) {
                return [
                    'id' => $f->id,
                    'original_name' => $f->original_name,
                    'file_size' => $f->file_size,
                    'mime_type' => $f->mime_type,
                    'download_url' => '/admin/enquiry-file/' . $f->id . '/download',
                ];
            })->values(),
        ]);
    }

    public function show(Enquiry $enquiry)
    {
    }

    public function print(Enquiry $enquiry)
    {
        $enquiry->load(['client:id,cl_name', 'items.costSheet:id,qty_unit', 'customItems']);

        $itemsTaxableTotal = (float) $enquiry->items_taxable_total;
        $transportGst = round(((float) $enquiry->transport_charge * (float) $enquiry->gst_percent) / 100, 2);

        $pdf = Pdf::loadView('Admin.enquiry-invoice', [
            'enquiry' => $enquiry,
            'itemsTotal' => round($itemsTaxableTotal, 2),
            'transportGst' => $transportGst,
        ])->setPaper('a4', 'portrait');

        $safeOrderNo = str_replace(['/', '\\'], '-', (string) $enquiry->enquiry_no);
        return $pdf->stream('enquiry-' . $safeOrderNo . '.pdf');
    }

    public function create()
    {
        $resourceNeo = $this->resourceNeo;
        $resourceNeo['mode'] = 'create';

        $clients = $this->getClients();
        $costSheetOptions = $this->getCostSheetOptions();

        return Inertia::render('Admin/EnquiryAddEditView', compact('resourceNeo', 'clients', 'costSheetOptions'));
    }

    public function pushToSalesOrder(Enquiry $enquiry)
    {
        $enquiry->load(['items.costSheet', 'client']);

        // Prepare items in the same format SalesOrderAddEditView expects
        $items = $enquiry->items->map(function ($item) {
            return [
                'cost_sheet_id' => $item->cost_sheet_id,
                'item_name'     => $item->item_name,
                'qty_mode'      => $item->qty_mode,
                'length'        => $item->length,
                'width'         => $item->width,
                'pieces'        => $item->pieces,
                'qty'           => $item->qty,
                'rate'          => $item->rate,
                'gst_percent'   => $item->gst_percent,
            ];
        })->values()->toArray();

        $formdata = [
            'enquiry_id'       => $enquiry->id,
            'enquiry_no'       => $enquiry->enquiry_no,
            'client_id'        => $enquiry->client_id,
            'product_type'     => $enquiry->product_type,
            'remark'           => 'From ' . $enquiry->enquiry_no,
            'transport_charge' => $enquiry->transport_charge,
            'gst_percent'      => $enquiry->gst_percent,
            'items'            => $items,
        ];

        $resourceNeo = (new \App\Http\Controllers\Admin\SalesOrderController)->getResourceNeo();
        $resourceNeo['mode'] = 'create';
        $resourceNeo['from_enquiry'] = true;

        $clients = (new \App\Http\Controllers\Admin\SalesOrderController)->getClients();
        $costSheetOptions = (new \App\Http\Controllers\Admin\SalesOrderController)->getCostSheetOptions();

        return Inertia::render('Admin/SalesOrderAddEditView', compact('formdata', 'resourceNeo', 'clients', 'costSheetOptions'));
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

        $enquiryDate = Carbon::parse($validated['enquiry_date'])->format('Y-m-d');
        $maxAttempt = 5;

        for ($attempt = 1; $attempt <= $maxAttempt; $attempt++) {
            DB::beginTransaction();
            try {
                [$enquiryNo, $prefix, $sequence, $fyCode] = $this->nextEnquiryNumber($validated['product_type'], $enquiryDate);
                $lineResult = $this->buildLineRows($validated['product_type'], $items);

                $enquiry = Enquiry::create([
                    'enquiry_no' => $enquiryNo,
                    'enquiry_prefix' => $prefix,
                    'enquiry_sequence' => $sequence,
                    'enquiry_fy' => $fyCode,
                    'enquiry_date' => $enquiryDate,
                    'client_id' => $validated['client_id'],
                    'product_type' => $validated['product_type'],
                    'remark' => $validated['remark'] ?? null,
                    'total_amount' => 0,
                ]);

                if (!empty($lineResult['rows'])) {
                    $enquiry->items()->createMany($lineResult['rows']);
                }

                // Save custom items
                $customItems = $this->normalizeCustomItems($request->custom_items ?? []);
                if (!empty($customItems)) {
                    $enquiry->customItems()->createMany($customItems);
                }

                $transportGst = round(($transportCharge * $orderGstPercent) / 100, 2);
                $totalAmount = round(
                    $lineResult['items_taxable_total'] +
                    $lineResult['items_gst_total'] +
                    $transportCharge +
                    $transportGst,
                    2
                );

                $enquiry->update([
                    'transport_charge' => $transportCharge,
                    'gst_percent' => $orderGstPercent,
                    'items_taxable_total' => $lineResult['items_taxable_total'],
                    'items_gst_total' => $lineResult['items_gst_total'],
                    'total_amount' => $totalAmount,
                ]);

                // Handle temp files uploaded via background requests
                if ($request->has('temp_files') && is_array($request->temp_files)) {
                    foreach ($request->temp_files as $tf) {
                        $oldPath = 'temp_enquiry_files/' . $tf['stored_name'];
                        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($oldPath)) {
                            $newDir = 'enquiry_files/' . $enquiry->id;
                            if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($newDir)) {
                                \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory($newDir);
                            }
                            $newPath = $newDir . '/' . $tf['stored_name'];
                            \Illuminate\Support\Facades\Storage::disk('local')->move($oldPath, $newPath);

                            $enquiry->files()->create([
                                'original_name' => $tf['original_name'],
                                'stored_name'   => $tf['stored_name'],
                                'mime_type'     => $tf['mime_type'],
                                'file_size'     => $tf['file_size'],
                            ]);
                        }
                    }
                }

                DB::commit();

                \ActivityLog::add([
                    'action' => 'added',
                    'module' => $this->resourceNeo['resourceName'],
                    'data_key' => $enquiry->enquiry_no,
                ]);

                return redirect()->route('enquiry.index')->with([
                    'message' => 'Enquiry Created Successfully !!',
                    'msg_type' => 'success',
                    'new_enquiry_id' => $enquiry->id,
                ]);
            } catch (QueryException $e) {
                DB::rollBack();
                if ($this->isDuplicateKeyException($e) && $attempt < $maxAttempt) {
                    continue;
                }

                return redirect()->back()->withErrors(['enquiry' => $e->getMessage()])->withInput();
            } catch (\Throwable $e) {
                DB::rollBack();
                return redirect()->back()->withErrors(['enquiry' => $e->getMessage()])->withInput();
            }
        }

        return redirect()->back()->withErrors([
            'enquiry' => 'Unable to create enquiry. Please try again.',
        ])->withInput();
    }

    public function edit(Enquiry $enquiry)
    {
        $enquiry->load(['items', 'customItems', 'files']);

        $formdata = [
            'id' => $enquiry->id,
            'enquiry_no' => $enquiry->enquiry_no,
            'enquiry_date' => $enquiry->enquiry_date,
            'client_id' => $enquiry->client_id,
            'product_type' => $enquiry->product_type,
            'remark' => $enquiry->remark,
            'transport_charge' => $enquiry->transport_charge,
            'gst_percent' => $enquiry->gst_percent,
            'items' => $enquiry->items->map(function ($item) {
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
            'custom_items' => $enquiry->customItems->map(function ($ci) {
                return [
                    'item_name' => $ci->item_name,
                    'qty' => $ci->qty,
                ];
            })->values(),
            'existing_files' => $enquiry->files->map(function ($f) {
                return [
                    'id' => $f->id,
                    'original_name' => $f->original_name,
                    'file_size' => $f->file_size,
                    'mime_type' => $f->mime_type,
                    'download_url' => '/admin/enquiry-file/' . $f->id . '/download',
                    'delete_url' => '/admin/enquiry-file/' . $f->id,
                ];
            })->values(),
        ];

        $resourceNeo = $this->resourceNeo;
        $resourceNeo['mode'] = 'edit';

        $clients = $this->getClients();
        $costSheetOptions = $this->getCostSheetOptions();

        return Inertia::render('Admin/EnquiryAddEditView', compact('formdata', 'resourceNeo', 'clients', 'costSheetOptions'));
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $validated = $this->validatePayload($request, $enquiry->id);

        $items = $this->normalizeItems($request->items ?? []);
        $transportCharge = round((float) ($validated['transport_charge'] ?? 0), 2);
        $orderGstPercent = round((float) ($validated['gst_percent'] ?? 18), 2);

        if (count($items) === 0) {
            return redirect()->back()->withErrors(['items' => 'Add at least one item.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $lineResult = $this->buildLineRows($validated['product_type'], $items);

            $enquiry->items()->delete();
            $enquiry->customItems()->delete();
            $enquiry->update([
                'enquiry_date' => Carbon::parse($validated['enquiry_date'])->format('Y-m-d'),
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
                $enquiry->items()->createMany($lineResult['rows']);
            }

            // Save custom items
            $customItems = $this->normalizeCustomItems($request->custom_items ?? []);
            if (!empty($customItems)) {
                $enquiry->customItems()->createMany($customItems);
            }

            $transportGst = round(($transportCharge * $orderGstPercent) / 100, 2);

            $enquiry->update([
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

            // Handle temp files uploaded via background requests
            if ($request->has('temp_files') && is_array($request->temp_files)) {
                foreach ($request->temp_files as $tf) {
                    $oldPath = 'temp_enquiry_files/' . $tf['stored_name'];
                    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($oldPath)) {
                        $newDir = 'enquiry_files/' . $enquiry->id;
                        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($newDir)) {
                            \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory($newDir);
                        }
                        $newPath = $newDir . '/' . $tf['stored_name'];
                        \Illuminate\Support\Facades\Storage::disk('local')->move($oldPath, $newPath);

                        $enquiry->files()->create([
                            'original_name' => $tf['original_name'],
                            'stored_name'   => $tf['stored_name'],
                            'mime_type'     => $tf['mime_type'],
                            'file_size'     => $tf['file_size'],
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['enquiry' => $e->getMessage()])->withInput();
        }

        \ActivityLog::add([
            'action' => 'updated',
            'module' => $this->resourceNeo['resourceName'],
            'data_key' => $enquiry->enquiry_no,
        ]);

        return redirect()->route('enquiry.index')->with([
            'message' => 'Enquiry Updated Successfully !!',
            'msg_type' => 'success',
        ]);
    }

    public function destroy(Enquiry $enquiry)
    {
        $enquiryNo = $enquiry->enquiry_no;
        $enquiry->load('files');
        foreach ($enquiry->files as $file) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($file->storagePath());
        }
        $enquiry->delete();

        \ActivityLog::add([
            'action' => 'deleted',
            'module' => $this->resourceNeo['resourceName'],
            'data_key' => $enquiryNo,
        ]);

        return redirect()->back()->with(['message' => 'Enquiry Deleted !!', 'msg_type' => 'success']);
    }

    public function bulkDestroy()
    {
        $ids = request('ids', []);
        // Delete physical files for each enquiry
        $enquiries = Enquiry::with('files')->whereIn('id', $ids)->get();
        foreach ($enquiries as $enq) {
            foreach ($enq->files as $file) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($file->storagePath());
            }
        }
        Enquiry::whereIn('id', $ids)->delete();

        $uname = (count($ids) > 50) ? 'Many' : implode(',', $ids);
        \ActivityLog::add([
            'action' => 'deleted',
            'module' => $this->resourceNeo['resourceName'],
            'data_key' => $uname,
        ]);

        return redirect()->back()->with(['message' => 'Selected Enquiries Deleted !!', 'msg_type' => 'success']);
    }

    public function uploadFiles(Request $request, Enquiry $enquiry)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file',
        ]);

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $storedName = uniqid('enq_', true) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = 'enquiry_files/' . $enquiry->id;
            $file->storeAs($path, $storedName, 'local');

            $record = $enquiry->files()->create([
                'original_name' => $originalName,
                'stored_name'   => $storedName,
                'mime_type'     => $file->getMimeType(),
                'file_size'     => $file->getSize(),
            ]);

            $uploaded[] = [
                'id'            => $record->id,
                'original_name' => $record->original_name,
                'file_size'     => $record->file_size,
                'mime_type'     => $record->mime_type,
                'download_url'  => '/admin/enquiry-file/' . $record->id . '/download',
                'delete_url'    => '/admin/enquiry-file/' . $record->id,
            ];
        }

        return response()->json(['files' => $uploaded]);
    }

    public function uploadTempFiles(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file',
        ]);

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $storedName = uniqid('temp_', true) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('temp_enquiry_files', $storedName, 'local');

            $uploaded[] = [
                'original_name' => $originalName,
                'stored_name'   => $storedName,
                'mime_type'     => $file->getMimeType(),
                'file_size'     => $file->getSize(),
            ];
        }

        return response()->json(['files' => $uploaded]);
    }

    public function deleteFile(EnquiryFile $enquiryFile)
    {
        \Illuminate\Support\Facades\Storage::disk('local')->delete($enquiryFile->storagePath());
        $enquiryFile->delete();

        return response()->json(['success' => true]);
    }

    public function downloadFile(EnquiryFile $enquiryFile)
    {
        $path = storage_path('app/' . $enquiryFile->storagePath());

        if (! file_exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->download($path, $enquiryFile->original_name);
    }

    protected function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'enquiry_date' => 'required|date',
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
            'custom_items' => 'nullable|array',
            'custom_items.*.item_name' => 'required|string|max:255',
            'custom_items.*.qty' => 'required|numeric|min:0',
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

    protected function normalizeCustomItems(array $customItems): array
    {
        $result = [];
        foreach ($customItems as $ci) {
            $name = isset($ci['item_name']) ? trim($ci['item_name']) : '';
            $qty = (float) ($ci['qty'] ?? 0);
            if (empty($name)) {
                continue;
            }
            $result[] = [
                'item_name' => $name,
                'qty' => round($qty, 4),
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
            $isDimensionQty = in_array($normalizedUnit, ['sqft',    'sqf', 'sqm'], true);
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

    protected function getClients(): Collection
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

    protected function getCostSheetOptions(): array
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

    protected function nextEnquiryNumber(string $productType, string $enquiryDate): array
    {
        $prefix = $this->prefixForType($productType);
        $fyCode = $this->fyCodeFromDate($enquiryDate);

        $maxSequence = (int) Enquiry::where('enquiry_prefix', $prefix)
            ->where('enquiry_fy', $fyCode)
            ->lockForUpdate()
            ->max('enquiry_sequence');

        $nextSequence = $maxSequence + 1;
        $enquiryNo = $prefix . str_pad((string) $nextSequence, 2, '0', STR_PAD_LEFT) . '/' . $fyCode;

        return [$enquiryNo, $prefix, $nextSequence, $fyCode];
    }

    protected function prefixForType(string $productType): string
    {
        return match ($productType) {
            'signage' => 'SE',
            'cabinet' => 'CE',
            'letters' => 'LE',
            default => throw new \InvalidArgumentException('Invalid product type.'),
        };
    }

    protected function fyCodeFromDate(string $enquiryDate): string
    {
        $date = Carbon::parse($enquiryDate);
        $startYear = ((int) $date->format('n') >= 4) ? (int) $date->format('Y') : ((int) $date->format('Y') - 1);
        $nextYear = $startYear + 1;

        return substr((string) $startYear, -2) . substr((string) $nextYear, -2);
    }

    protected function isDuplicateKeyException(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }
}
