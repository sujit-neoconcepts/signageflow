<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - {{ $employee?->emp_code }} - {{ $payRun->run_code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 20px; }
        .header { border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 14px; }
        .company-name { font-size: 18px; font-weight: bold; color: #1e3a8a; }
        .subtitle { font-size: 12px; color: #4b5563; margin-top: 2px; }
        .meta-table, .pay-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td { padding: 4px 6px; border: 1px solid #d1d5db; font-size: 10px; }
        .meta-label { font-weight: bold; background: #f3f4f6; width: 18%; }
        .pay-table th { background: #1e3a8a; color: #ffffff; padding: 6px; text-align: left; font-size: 10px; }
        .pay-table td { padding: 5px 6px; border: 1px solid #d1d5db; font-size: 10px; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; background: #f3f4f6; }
        .net-box { background: #eff6ff; border: 1px solid #93c5fd; padding: 10px; margin-top: 10px; font-size: 13px; font-weight: bold; color: #1e3a8a; }
        .footer { margin-top: 20px; font-size: 9px; color: #6b7280; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ config('app.name', 'SignageFlow') }} — Payslip</div>
        <div class="subtitle">
            Pay Period: {{ $payRun->period_start?->format('d M Y') }} to {{ $payRun->period_end?->format('d M Y') }}
            | Run Code: {{ $payRun->run_code }}
            | Frequency: {{ ucfirst($payRun->pay_frequency ?? 'monthly') }}
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Employee Code</td>
            <td>{{ $employee?->emp_code ?? '-' }}</td>
            <td class="meta-label">Employee Name</td>
            <td>{{ $employee?->full_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Department</td>
            <td>{{ $employee?->department?->name ?? '-' }}</td>
            <td class="meta-label">Designation</td>
            <td>{{ $employee?->designation?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Payable Days</td>
            <td>{{ number_format((float) $item->payable_days, 2) }}</td>
            <td class="meta-label">LOP Days / OT Hrs</td>
            <td>{{ number_format((float) $item->lop_days, 2) }} / {{ number_format((float) $item->ot_hours, 2) }}</td>
        </tr>
        <tr>
            <td class="meta-label">Bank Account</td>
            <td>{{ $employee?->bank_account_no ? 'XXXX' . substr($employee->bank_account_no, -4) : '-' }}</td>
            <td class="meta-label">UAN / ESIC</td>
            <td>{{ $employee?->uan_no ?? '-' }} / {{ $employee?->esic_no ?? '-' }}</td>
        </tr>
    </table>

    @php
        $earnings = $item->earnings_json ?? [];
        $deductions = $item->deductions_json ?? [];
        if ($item->claim_reimbursement_amount > 0) {
            $earnings['E-Claims Reimbursement'] = $item->claim_reimbursement_amount;
        }
        if ($item->attendance_allowance_amount > 0) {
            $earnings['Attendance Allowance'] = $item->attendance_allowance_amount;
        }
        if ($item->arrear_amount > 0) {
            $earnings['Arrears'] = $item->arrear_amount;
        }
        if ($item->fnf_settlement_amount > 0) {
            $earnings['FnF Settlement'] = $item->fnf_settlement_amount;
        }
        if ($item->epf_employee > 0) {
            $deductions['EPF (Employee)'] = $item->epf_employee;
        }
        if ($item->esic_employee > 0) {
            $deductions['ESIC (Employee)'] = $item->esic_employee;
        }
        if ($item->pt_amount > 0) {
            $deductions['Professional Tax (PT)'] = $item->pt_amount;
        }
        if ($item->lwf_amount > 0) {
            $deductions['LWF'] = $item->lwf_amount;
        }
        if ($item->tds_amount > 0) {
            $deductions['TDS'] = $item->tds_amount;
        }
        if ($item->loan_emi_amount > 0) {
            $deductions['Loan EMI'] = $item->loan_emi_amount;
        }
        $eKeys = array_keys($earnings);
        $dKeys = array_keys($deductions);
        $rows = max(count($eKeys), count($dKeys), 1);
    @endphp

    <table class="pay-table">
        <thead>
            <tr>
                <th style="width: 32%;">Earnings</th>
                <th style="width: 18%;" class="text-right">Amount (&#8377;)</th>
                <th style="width: 32%;">Deductions</th>
                <th style="width: 18%;" class="text-right">Amount (&#8377;)</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 0; $i < $rows; $i++)
                @php
                    $ek = $eKeys[$i] ?? null;
                    $dk = $dKeys[$i] ?? null;
                @endphp
                <tr>
                    <td>{{ $ek ?? '' }}</td>
                    <td class="text-right">{{ $ek !== null ? number_format((float) $earnings[$ek], 2) : '' }}</td>
                    <td>{{ $dk ?? '' }}</td>
                    <td class="text-right">{{ $dk !== null ? number_format((float) $deductions[$dk], 2) : '' }}</td>
                </tr>
            @endfor
            <tr class="total-row">
                <td>Gross Earnings</td>
                <td class="text-right">{{ number_format((float) $item->gross_earnings, 2) }}</td>
                <td>Total Deductions</td>
                <td class="text-right">{{ number_format((float) $item->total_deductions, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($item->employer_contributions_json))
        <table class="meta-table">
            <tr>
                <td class="meta-label">Employer Contributions</td>
                <td>
                    @foreach($item->employer_contributions_json as $k => $v)
                        {{ $k }}: &#8377;{{ number_format((float) $v, 2) }}@if(!$loop->last) | @endif
                    @endforeach
                </td>
            </tr>
        </table>
    @endif

    <div class="net-box">
        Net Payable: &#8377;{{ number_format((float) $item->net_wages, 2) }}
    </div>

    <div class="footer">
        This is a system-generated payslip from SignageFlow HRMS and does not require a physical signature.
    </div>
</body>
</html>
