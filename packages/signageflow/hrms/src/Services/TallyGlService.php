<?php

namespace SignageFlow\Hrms\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SignageFlow\Hrms\Models\GlBatch;
use SignageFlow\Hrms\Models\PayRun;

class TallyGlService
{
    public function createBatch(PayRun $payRun, string $voucherDate, ?string $narration = null): GlBatch
    {
        $mapping = $payRun->payGroup?->tally_ledger_mapping ?? [
            'salary_expense' => 'Salaries & Wages',
            'salary_payable' => 'Salary Payable',
            'epf_payable' => 'EPF Payable',
            'esic_payable' => 'ESIC Payable',
            'pt_payable' => 'Professional Tax Payable',
            'tds_payable' => 'TDS Payable',
            'loan_recovery' => 'Staff Loans & Advances',
            'reimbursements' => 'Staff Reimbursements Payable',
        ];

        $items = $payRun->items;
        $gross = round($items->sum('gross_earnings'), 2);
        $epfEmp = round($items->sum('epf_employee'), 2);
        $epfEmpr = round($items->sum('epf_employer'), 2);
        $esicEmp = round($items->sum('esic_employee'), 2);
        $esicEmpr = round($items->sum('esic_employer'), 2);
        $pt = round($items->sum('pt_amount'), 2);
        $tds = round($items->sum('tds_amount'), 2);
        $loan = round($items->sum('loan_emi_amount'), 2);
        $net = round($items->sum('net_wages'), 2);

        $entries = [];
        if ($gross > 0) {
            $entries[] = ['ledger' => $mapping['salary_expense'] ?? 'Salaries & Wages', 'type' => 'Dr', 'amount' => $gross];
        }
        if ($epfEmpr > 0) {
            $entries[] = ['ledger' => 'Employer EPF Contribution', 'type' => 'Dr', 'amount' => $epfEmpr];
        }
        if ($esicEmpr > 0) {
            $entries[] = ['ledger' => 'Employer ESIC Contribution', 'type' => 'Dr', 'amount' => $esicEmpr];
        }
        if ($epfEmp + $epfEmpr > 0) {
            $entries[] = ['ledger' => $mapping['epf_payable'] ?? 'EPF Payable', 'type' => 'Cr', 'amount' => round($epfEmp + $epfEmpr, 2)];
        }
        if ($esicEmp + $esicEmpr > 0) {
            $entries[] = ['ledger' => $mapping['esic_payable'] ?? 'ESIC Payable', 'type' => 'Cr', 'amount' => round($esicEmp + $esicEmpr, 2)];
        }
        if ($pt > 0) {
            $entries[] = ['ledger' => $mapping['pt_payable'] ?? 'Professional Tax Payable', 'type' => 'Cr', 'amount' => $pt];
        }
        if ($tds > 0) {
            $entries[] = ['ledger' => $mapping['tds_payable'] ?? 'TDS Payable', 'type' => 'Cr', 'amount' => $tds];
        }
        if ($loan > 0) {
            $entries[] = ['ledger' => $mapping['loan_recovery'] ?? 'Staff Loans & Advances', 'type' => 'Cr', 'amount' => $loan];
        }
        if ($net > 0) {
            $entries[] = ['ledger' => $mapping['salary_payable'] ?? 'Salary Payable', 'type' => 'Cr', 'amount' => $net];
        }

        $totalDr = round(collect($entries)->where('type', 'Dr')->sum('amount'), 2);
        $totalCr = round(collect($entries)->where('type', 'Cr')->sum('amount'), 2);

        return GlBatch::create([
            'batch_code' => 'GL-'.$payRun->run_code,
            'pay_run_id' => $payRun->id,
            'voucher_date' => $voucherDate,
            'narration' => $narration ?: "Payroll Journal for {$payRun->run_code}",
            'entries_json' => $entries,
            'total_debit' => $totalDr,
            'total_credit' => $totalCr,
            'created_by' => auth()->id(),
        ]);
    }

    public function toTallyXml(GlBatch $batch): string
    {
        $date = $batch->voucher_date->format('Ymd');
        $narration = htmlspecialchars((string) $batch->narration, ENT_XML1);

        $ledgerXml = '';
        foreach ($batch->entries_json as $entry) {
            $ledger = htmlspecialchars((string) $entry['ledger'], ENT_XML1);
            $isDeemedPositive = $entry['type'] === 'Dr' ? 'Yes' : 'No';
            $amount = $entry['type'] === 'Dr' ? -abs((float) $entry['amount']) : abs((float) $entry['amount']);

            $ledgerXml .= <<<XML
            <ALLLEDGERENTRIES.LIST>
                <LEDGERNAME>{$ledger}</LEDGERNAME>
                <ISDEEMEDPOSITIVE>{$isDeemedPositive}</ISDEEMEDPOSITIVE>
                <AMOUNT>{$amount}</AMOUNT>
            </ALLLEDGERENTRIES.LIST>
XML;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
    <HEADER>
        <TALLYREQUEST>Import Data</TALLYREQUEST>
    </HEADER>
    <BODY>
        <IMPORTDATA>
            <REQUESTDESC>
                <REPORTNAME>Vouchers</REPORTNAME>
            </REQUESTDESC>
            <REQUESTDATA>
                <TALLYMESSAGE xmlns:UDF="TallyUDF">
                    <VOUCHER VCHTYPE="Journal" ACTION="Create">
                        <DATE>{$date}</DATE>
                        <VOUCHERTYPENAME>Journal</VOUCHERTYPENAME>
                        <NARRATION>{$narration}</NARRATION>
                        {$ledgerXml}
                    </VOUCHER>
                </TALLYMESSAGE>
            </REQUESTDATA>
        </IMPORTDATA>
    </BODY>
</ENVELOPE>
XML;
    }

    public function toSpreadsheet(GlBatch $batch): Xlsx
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Batch Code', 'Voucher Date', 'Ledger', 'Dr/Cr', 'Amount', 'Narration'], null, 'A1');

        $row = 2;
        foreach ($batch->entries_json as $entry) {
            $sheet->fromArray([
                $batch->batch_code,
                $batch->voucher_date->format('Y-m-d'),
                $entry['ledger'],
                $entry['type'],
                $entry['amount'],
                $batch->narration,
            ], null, 'A'.$row++);
        }

        return new Xlsx($spreadsheet);
    }
}
