<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Payroll;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\BankTemplate;
use SignageFlow\Hrms\Models\GlBatch;
use SignageFlow\Hrms\Models\PayGroup;
use SignageFlow\Hrms\Models\PayRun;
use SignageFlow\Hrms\Models\PayRunItem;
use SignageFlow\Hrms\Services\BankAndTallyExportService;
use SignageFlow\Hrms\Services\FinancialYearHelper;
use SignageFlow\Hrms\Services\PayRunProcessingService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class PayRunController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsPayroll_list')->only(['index', 'show', 'downloadPayslip', 'exportBank', 'exportTallyXml', 'exportGlExcel']);
        $this->middleware('can:hrmsPayroll_create')->only(['store', 'initialize', 'createGl']);
        $this->middleware('can:hrmsPayroll_edit')->only(['updateItem', 'importOverrides', 'submit']);
        $this->middleware('can:hrmsPayroll_delete')->only(['destroyItem']);
        $this->middleware('can:hrmsPayroll_approveL1')->only(['approve']);
        $this->middleware('can:hrmsPayroll_publish')->only(['publish']);
    }

    public function index(Request $request)
    {
        $perPage = (int) ($request->query('perPage') ?? 15);
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($q) => Collection::wrap($value)->each(fn ($v) => $q->orWhere('hrms_pay_runs.run_code', 'LIKE', "%{$v}%")));
        });

        $query = PayRun::query()
            ->leftJoin('hrms_pay_groups as pg', 'hrms_pay_runs.pay_group_id', '=', 'pg.id')
            ->select(['hrms_pay_runs.*', 'pg.name as pay_group_name']);

        $payRuns = QueryBuilder::for($query)
            ->defaultSort('-hrms_pay_runs.id')
            ->allowedSorts(['run_code', 'pay_frequency', 'period_start', 'total_net', 'stage'])
            ->allowedFilters([AllowedFilter::exact('stage', 'hrms_pay_runs.stage'), AllowedFilter::exact('pay_frequency', 'hrms_pay_runs.pay_frequency'), $global])
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Hrms/Payroll/PayRunIndex', [
            'payRuns' => $payRuns,
            'payGroups' => PayGroup::where('is_active', true)->orderBy('name')->get(['id', 'name', 'pay_frequency']),
            'resourceNeo' => [
                'resourceName' => 'hrms.payroll.payruns',
                'resourceTitle' => 'PayRuns',
                'actions' => ['c', 'r'],
            ],
        ])->table(function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('run_code', 'Run Code', searchable: true, sortable: true)
                ->column('pay_group_name', 'Pay Group', searchable: false, sortable: false)
                ->column('pay_frequency', 'Cycle', searchable: false, sortable: true)
                ->column('period_start', 'Period', searchable: false, sortable: true)
                ->column('total_gross', 'Gross', searchable: false, sortable: false)
                ->column('total_deductions', 'Deductions', searchable: false, sortable: false)
                ->column('total_net', 'Net Payable', searchable: false, sortable: true)
                ->column('stage', 'Stage', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->selectFilter(key: 'pay_frequency', label: 'Cycle', options: ['monthly' => 'Monthly', 'weekly' => 'Weekly'], noFilterOptionLabel: 'All')
                ->selectFilter(key: 'stage', label: 'Stage', options: [
                    'initialized' => 'Stage 1: Initialized',
                    'pending_l1' => 'Stage 2: Pending L1',
                    'pending_l2' => 'Stage 2: Pending L2',
                    'approved' => 'Approved',
                    'published' => 'Stage 3: Published',
                ], noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50]);
        });
    }

    public function store(Request $request, PayRunProcessingService $processor)
    {
        $data = $request->validate([
            'pay_group_id' => 'required|exists:hrms_pay_groups,id',
            'period_year' => 'required|integer|min:2020|max:2100',
            'period_month' => 'required|integer|min:1|max:12',
            'week_number' => 'nullable|integer|min:1|max:53',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'sync_options' => 'nullable|array',
        ]);

        $group = PayGroup::findOrFail($data['pay_group_id']);
        $suffix = $group->pay_frequency === 'weekly' && ! empty($data['week_number']) ? '-W'.$data['week_number'] : '';
        $runCode = sprintf('PR-%s-%04d%02d%s-%s', $group->code, $data['period_year'], $data['period_month'], $suffix, substr(uniqid(), -4));

        $payRun = PayRun::create([
            'run_code' => $runCode,
            'pay_group_id' => $group->id,
            'pay_frequency' => $group->pay_frequency,
            'financial_year' => FinancialYearHelper::forDate($data['period_start']),
            'period_year' => $data['period_year'],
            'period_month' => $data['period_month'],
            'week_number' => $data['week_number'] ?? null,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'sync_options' => $data['sync_options'] ?? [
                'absent' => true,
                'leave' => true,
                'late' => true,
                'early_out' => false,
                'ot' => true,
                'break_hours' => false,
            ],
            'stage' => 'initialized',
        ]);

        $processor->initializeItems($payRun);

        return redirect()->route('hrms.payroll.payruns.show', $payRun->id)
            ->with(['message' => "PayRun {$runCode} initialized.", 'msg_type' => 'success']);
    }

    public function show(Request $request, PayRun $payRun)
    {
        $tab = $request->input('tab', 'initialized'); // initialized | non_initialized | arrear | fnf
        $perPage = (int) ($request->query('perPage') ?? 25);

        $query = PayRunItem::query()
            ->leftJoin('hrms_employees as emp', 'hrms_pay_run_items.employee_id', '=', 'emp.id')
            ->leftJoin('hrms_departments as dept', 'emp.department_id', '=', 'dept.id')
            ->leftJoin('hrms_designations as desig', 'emp.designation_id', '=', 'desig.id')
            ->leftJoin('hrms_categories as cat', 'emp.category_id', '=', 'cat.id')
            ->where('hrms_pay_run_items.pay_run_id', $payRun->id)
            ->select([
                'hrms_pay_run_items.*',
                'emp.emp_code as emp_code',
                'emp.full_name as employee_name',
                'emp.employment_status as emp_status',
                'dept.name as department_name',
                'desig.name as designation_name',
                'cat.name as category_name',
            ]);

        match ($tab) {
            'non_initialized' => $query->where('hrms_pay_run_items.is_initialized', false),
            'arrear' => $query->where('hrms_pay_run_items.run_type', 'arrear'),
            'fnf' => $query->where('hrms_pay_run_items.run_type', 'fnf'),
            default => $query->where('hrms_pay_run_items.is_initialized', true),
        };

        $global = AllowedFilter::callback('global', function ($q, $value) {
            $q->where(fn ($sub) => Collection::wrap($value)->each(fn ($v) => $sub->orWhere('emp.emp_code', 'LIKE', "%{$v}%")->orWhere('emp.full_name', 'LIKE', "%{$v}%")));
        });

        $items = QueryBuilder::for($query)
            ->defaultSort('emp.emp_code')
            ->allowedSorts([
                AllowedSort::field('emp_code', 'emp.emp_code'),
                AllowedSort::field('employee_name', 'emp.full_name'),
                'net_wages',
                'approval_status',
            ])
            ->allowedFilters([
                AllowedFilter::exact('department_id', 'emp.department_id'),
                AllowedFilter::exact('approval_status', 'hrms_pay_run_items.approval_status'),
                $global,
            ])
            ->paginate($perPage)
            ->withQueryString();

        $payRun->load('payGroup');

        return Inertia::render('Hrms/Payroll/PayRunShow', [
            'tab' => $tab,
            'payRun' => $payRun,
            'items' => $items,
            'bankTemplates' => BankTemplate::where('is_active', true)->get(['id', 'name', 'format']),
            'glBatch' => GlBatch::where('pay_run_id', $payRun->id)->first(),
            'resourceNeo' => [
                'resourceName' => 'hrms.payroll.payruns',
                'resourceTitle' => $payRun->run_code,
                'actions' => ['r', 'u', 'd'],
            ],
        ])->table(function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('emp_code', 'Emp Code', searchable: true, sortable: true)
                ->column('employee_name', 'Employee Name', searchable: true, sortable: true)
                ->column('emp_status', 'Status', searchable: false, sortable: false)
                ->column('department_name', 'Department', searchable: false, sortable: false)
                ->column('designation_name', 'Designation', searchable: false, sortable: false)
                ->column('category_name', 'Category', searchable: false, sortable: false)
                ->column('net_wages', 'Net Wages', searchable: false, sortable: true)
                ->column('approval_status', 'Approval Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->perPageOptions([10, 25, 50, 100]);
        });
    }

    public function initialize(Request $request, PayRun $payRun, PayRunProcessingService $processor)
    {
        if ($payRun->stage !== 'initialized') {
            return back()->with(['message' => 'Only Stage 1 PayRuns can be re-initialized.', 'msg_type' => 'warning']);
        }

        if ($request->has('sync_options')) {
            $payRun->update(['sync_options' => $request->input('sync_options')]);
        }
        $processor->initializeItems($payRun->fresh());

        return back()->with(['message' => 'PayRun synced with live Attendance, Leave, Claims & Loans.', 'msg_type' => 'success']);
    }

    public function updateItem(Request $request, PayRunItem $payRunItem, PayRunProcessingService $processor)
    {
        $data = $request->validate([
            'payable_days' => 'required|numeric|min:0|max:31',
            'arrear_amount' => 'nullable|numeric|min:0',
            'fnf_settlement_amount' => 'nullable|numeric',
            'tds_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $newNet = round(max(0, $payRunItem->gross_earnings + $payRunItem->claim_reimbursement_amount + ($data['arrear_amount'] ?? 0) + ($data['fnf_settlement_amount'] ?? 0) - $payRunItem->total_deductions - ($data['tds_amount'] ?? 0)), 2);

        $payRunItem->update($data + ['net_wages' => $newNet]);
        $processor->refreshTotals($payRunItem->payRun);

        return back()->with(['message' => 'PayRun row updated.', 'msg_type' => 'success']);
    }

    public function destroyItem(PayRunItem $payRunItem, PayRunProcessingService $processor)
    {
        $payRun = $payRunItem->payRun;
        $payRunItem->delete();
        $processor->refreshTotals($payRun);

        return back()->with(['message' => 'Employee removed from PayRun.', 'msg_type' => 'success']);
    }

    public function importOverrides(Request $request, PayRun $payRun, PayRunProcessingService $processor)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);
        $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, true);
        $updated = 0;

        foreach ($rows as $idx => $row) {
            if ($idx === 1 || empty(trim((string) ($row['A'] ?? '')))) {
                continue;
            }
            $item = PayRunItem::where('pay_run_id', $payRun->id)
                ->whereHas('employee', fn ($q) => $q->where('emp_code', trim((string) $row['A'])))
                ->first();
            if (! $item) {
                continue;
            }
            $arrear = (float) ($row['B'] ?? $item->arrear_amount);
            $tds = (float) ($row['C'] ?? $item->tds_amount);
            $net = round(max(0, $item->gross_earnings + $item->claim_reimbursement_amount + $arrear + $item->fnf_settlement_amount - $item->total_deductions - $tds), 2);
            $item->update(['arrear_amount' => $arrear, 'tds_amount' => $tds, 'net_wages' => $net]);
            $updated++;
        }

        $processor->refreshTotals($payRun);

        return back()->with(['message' => "Imported overrides for {$updated} employees.", 'msg_type' => 'success']);
    }

    public function submit(PayRun $payRun, PayRunProcessingService $processor)
    {
        $processor->submitForApproval($payRun);

        return back()->with(['message' => 'PayRun submitted to Stage 2 (Approval).', 'msg_type' => 'success']);
    }

    public function approve(PayRun $payRun, PayRunProcessingService $processor)
    {
        $processor->approveStage($payRun, auth()->id());

        return back()->with(['message' => 'PayRun approval recorded.', 'msg_type' => 'success']);
    }

    public function publish(PayRun $payRun, PayRunProcessingService $processor)
    {
        if ($payRun->stage !== 'approved') {
            return back()->with(['message' => 'PayRun must be approved before publishing payslips.', 'msg_type' => 'warning']);
        }
        $processor->publish($payRun, auth()->id());

        return back()->with(['message' => 'Payslips generated and published to Web & Mobile!', 'msg_type' => 'success']);
    }

    public function downloadPayslip(PayRunItem $payRunItem)
    {
        $disk = config('hrms.storage_disk', 'local');
        if (! $payRunItem->payslip_pdf_path || ! Storage::disk($disk)->exists($payRunItem->payslip_pdf_path)) {
            abort(404, 'Payslip PDF not generated yet.');
        }

        return Storage::disk($disk)->download($payRunItem->payslip_pdf_path, "payslip-{$payRunItem->employee->emp_code}.pdf");
    }

    public function exportBank(Request $request, PayRun $payRun, BankAndTallyExportService $exporter)
    {
        $tpl = $request->filled('template_id') ? BankTemplate::find($request->input('template_id')) : $payRun->payGroup?->defaultBankTemplate;
        $built = $exporter->buildBankSpreadsheet($payRun, $tpl);
        $ext = $built['format'];

        return response()->streamDownload(function () use ($built) {
            $built['writer']->save('php://output');
        }, "bank-submission-{$payRun->run_code}.{$ext}");
    }

    public function createGl(PayRun $payRun, BankAndTallyExportService $exporter)
    {
        $batch = $exporter->createGlBatch($payRun, auth()->id());

        return back()->with(['message' => "General Ledger batch {$batch->batch_code} created (DR: {$batch->total_debit}, CR: {$batch->total_credit}).", 'msg_type' => 'success']);
    }

    public function exportTallyXml(PayRun $payRun, BankAndTallyExportService $exporter)
    {
        $batch = GlBatch::where('pay_run_id', $payRun->id)->first() ?? $exporter->createGlBatch($payRun, auth()->id());
        $xml = $exporter->toTallyXml($batch);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"tally-gl-{$payRun->run_code}.xml\"",
        ]);
    }

    public function exportGlExcel(PayRun $payRun, BankAndTallyExportService $exporter)
    {
        $batch = GlBatch::where('pay_run_id', $payRun->id)->first() ?? $exporter->createGlBatch($payRun, auth()->id());
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Batch Code', 'Voucher Date', 'Ledger Name', 'Dr/Cr', 'Amount', 'Narration'], null, 'A1');

        $row = 2;
        foreach ($batch->entries_json as $entry) {
            $sheet->fromArray([
                $batch->batch_code,
                Carbon::parse($batch->voucher_date)->format('Y-m-d'),
                $entry['ledger'],
                $entry['type'],
                $entry['amount'],
                $batch->narration,
            ], null, 'A'.$row++);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "gl-report-{$payRun->run_code}.xlsx");
    }
}
