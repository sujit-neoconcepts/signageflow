<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Payroll;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\BankTemplate;
use SignageFlow\Hrms\Models\GlBatch;
use SignageFlow\Hrms\Models\PayRun;
use SignageFlow\Hrms\Services\BankAndTallyExportService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BankTallyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsPayroll_list')->only(['index', 'downloadBankFile', 'downloadTallyXml', 'downloadGlExcel']);
        $this->middleware('can:hrmsPayroll_create')->only(['createGlBatch']);
    }

    public function index(Request $request)
    {
        $perPage = (int) ($request->query('perPage') ?? 15);
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($q) => Collection::wrap($value)->each(fn ($v) => $q->orWhere('hrms_pay_runs.run_code', 'LIKE', "%{$v}%")));
        });

        $query = PayRun::query()
            ->leftJoin('hrms_pay_groups as pg', 'hrms_pay_runs.pay_group_id', '=', 'pg.id')
            ->whereIn('hrms_pay_runs.stage', ['approved', 'published'])
            ->select(['hrms_pay_runs.*', 'pg.name as pay_group_name']);

        $payRuns = QueryBuilder::for($query)
            ->defaultSort('-hrms_pay_runs.id')
            ->allowedSorts(['run_code', 'period_start', 'total_net', 'stage'])
            ->allowedFilters([AllowedFilter::exact('stage', 'hrms_pay_runs.stage'), $global])
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Hrms/Payroll/BankAndTally', [
            'payRuns' => $payRuns,
            'bankTemplates' => BankTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name', 'format']),
            'glBatches' => GlBatch::with('payRun:id,run_code')->latest('id')->limit(30)->get(),
            'resourceNeo' => [
                'resourceName' => 'hrms.payroll.bankTally',
                'resourceTitle' => 'Bank Submission & Tally GL',
                'actions' => ['r', 'c'],
            ],
        ])->table(function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('run_code', 'PayRun Code', searchable: true, sortable: true)
                ->column('pay_group_name', 'Pay Group', searchable: false, sortable: false)
                ->column('period_start', 'Period Start', searchable: false, sortable: true)
                ->column('total_gross', 'Gross', searchable: false, sortable: false)
                ->column('total_deductions', 'Deductions', searchable: false, sortable: false)
                ->column('total_net', 'Net Payable', searchable: false, sortable: true)
                ->column('stage', 'Stage', searchable: false, sortable: true)
                ->column(label: 'Exports')
                ->perPageOptions([10, 15, 30, 50]);
        });
    }

    public function downloadBankFile(Request $request, PayRun $payRun, BankAndTallyExportService $exporter)
    {
        $tpl = $request->filled('template_id') ? BankTemplate::find($request->input('template_id')) : $payRun->payGroup?->defaultBankTemplate;
        $built = $exporter->buildBankSpreadsheet($payRun, $tpl);
        $ext = $built['format'];

        return response()->streamDownload(function () use ($built) {
            $built['writer']->save('php://output');
        }, "bank-submission-{$payRun->run_code}.{$ext}");
    }

    public function createGlBatch(PayRun $payRun, BankAndTallyExportService $exporter)
    {
        $batch = $exporter->createGlBatch($payRun, auth()->id());

        return back()->with(['message' => "GL Batch {$batch->batch_code} created.", 'msg_type' => 'success']);
    }

    public function downloadTallyXml(GlBatch $batch, BankAndTallyExportService $exporter)
    {
        $xml = $exporter->toTallyXml($batch);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"tally-gl-{$batch->batch_code}.xml\"",
        ]);
    }

    public function downloadGlExcel(GlBatch $batch)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Batch Code', 'Voucher Date', 'Ledger Name', 'Dr/Cr', 'Amount', 'Narration'], null, 'A1');

        $row = 2;
        foreach ($batch->entries_json ?? [] as $entry) {
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
        }, "gl-report-{$batch->batch_code}.xlsx");
    }
}
