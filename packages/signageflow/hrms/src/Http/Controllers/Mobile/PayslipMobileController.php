<?php

namespace SignageFlow\Hrms\Http\Controllers\Mobile;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\PayRunItem;

class PayslipMobileController extends Controller
{
    public function index(Request $request)
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();
        if (! $employee) {
            return response()->json(['payslips' => []]);
        }

        $items = PayRunItem::query()
            ->with('payRun:id,run_code,pay_frequency,period_start,period_end,period_year,period_month')
            ->where('employee_id', $employee->id)
            ->where('is_published', true)
            ->latest('id')
            ->limit(24)
            ->get()
            ->map(fn (PayRunItem $item) => [
                'id' => $item->id,
                'run_code' => $item->payRun?->run_code,
                'frequency' => $item->payRun?->pay_frequency,
                'period_label' => $item->payRun?->period_start?->format('M Y') ?? '-',
                'period_start' => $item->payRun?->period_start?->format('Y-m-d'),
                'period_end' => $item->payRun?->period_end?->format('Y-m-d'),
                'payable_days' => $item->payable_days,
                'gross_earnings' => $item->gross_earnings,
                'total_deductions' => $item->total_deductions,
                'net_wages' => $item->net_wages,
                'download_url' => route('api.mobile.hrms.payslips.download', $item->id, false),
            ]);

        return response()->json(['payslips' => $items]);
    }

    public function download(Request $request, PayRunItem $payRunItem)
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();
        if (! $employee || $payRunItem->employee_id !== $employee->id || ! $payRunItem->is_published) {
            abort(403, 'Unauthorized payslip access.');
        }

        $disk = config('hrms.storage_disk', 'local');
        if (! $payRunItem->payslip_pdf_path || ! Storage::disk($disk)->exists($payRunItem->payslip_pdf_path)) {
            abort(404, 'Payslip PDF not found.');
        }

        return Storage::disk($disk)->download($payRunItem->payslip_pdf_path, "payslip-{$employee->emp_code}.pdf");
    }
}
