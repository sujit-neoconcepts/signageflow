<?php

namespace SignageFlow\Hrms\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SignageFlow\Hrms\Models\BankTemplate;
use SignageFlow\Hrms\Models\GlBatch;
use SignageFlow\Hrms\Models\PayRun;

class BankAndTallyExportService
{
    public function buildBankSpreadsheet(PayRun $payRun, ?BankTemplate $template = null): array
    {
        $payRun->load('items.employee');
        $mapping = $template?->columns_mapping ?? [
            'Emp Code' => 'emp_code',
            'Beneficiary Name' => 'full_name',
            'Bank Name' => 'bank_name',
            'Account Number' => 'bank_account_no',
            'IFSC Code' => 'bank_ifsc',
            'Net Payable' => 'net_wages',
            'PayRun Ref' => 'run_code',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(array_keys($mapping), null, 'A1');

        $row = 2;
        foreach ($payRun->items->where('is_initialized', true) as $item) {
            $emp = $item->employee;
            $line = [];
            foreach ($mapping as $field) {
                $line[] = match ($field) {
                    'emp_code' => $emp?->emp_code,
                    'full_name' => $emp?->full_name,
                    'bank_name' => $emp?->bank_name,
                    'bank_account_no' => $emp?->bank_account_no,
                    'bank_ifsc' => $emp?->bank_ifsc,
                    'net_wages' => $item->net_wages,
                    'run_code' => $payRun->run_code,
                    default => '',
                };
            }
            $sheet->fromArray($line, null, 'A'.$row++);
        }

        $format = $template?->format ?? 'csv';

        return [
            'spreadsheet' => $spreadsheet,
            'format' => $format,
            'writer' => $format === 'xlsx' ? new Xlsx($spreadsheet) : new Csv($spreadsheet),
        ];
    }

    public function createGlBatch(PayRun $payRun, int $userId): GlBatch
    {
        $payRun->load(['payGroup', 'items']);
        $items = $payRun->items->where('is_initialized', true);

        $ledgers = $payRun->payGroup?->tally_ledger_mapping ?? [
            'salary_expense' => 'Salaries & Wages',
            'salary_payable' => 'Salary Payable',
            'epf_payable' => 'EPF Payable',
            'esic_payable' => 'ESIC Payable',
            'pt_payable' => 'Professional Tax Payable',
            'tds_payable' => 'TDS Payable',
            'loan_recovery' => 'Staff Loans & Advances',
            'reimbursements' => 'Staff Reimbursements Payable',
        ];

        $gross = round($items->sum('gross_earnings') + $items->sum('arrear_amount') + $items->sum('fnf_settlement_amount'), 2);
        $claims = round($items->sum('claim_reimbursement_amount'), 2);
        $epfEr = round($items->sum('epf_employer'), 2);
        $esicEr = round($items->sum('esic_employer'), 2);

        $epfTotal = round($items->sum('epf_employee') + $epfEr, 2);
        $esicTotal = round($items->sum('esic_employee') + $esicEr, 2);
        $pt = round($items->sum('pt_amount'), 2);
        $tds = round($items->sum('tds_amount'), 2);
        $loan = round($items->sum('loan_emi_amount'), 2);
        $net = round($items->sum('net_wages'), 2);

        $entries = [];
        $push = function (string $ledger, string $type, float $amount) use (&$entries) {
            if ($amount > 0) {
                $entries[] = ['ledger' => $ledger, 'type' => $type, 'amount' => round($amount, 2)];
            }
        };

        $push($ledgers['salary_expense'] ?? 'Salaries & Wages', 'DR', $gross + $epfEr + $esicEr);
        $push($ledgers['reimbursements'] ?? 'Staff Reimbursements Payable', 'DR', $claims);

        $push($ledgers['salary_payable'] ?? 'Salary Payable', 'CR', $net);
        $push($ledgers['epf_payable'] ?? 'EPF Payable', 'CR', $epfTotal);
        $push($ledgers['esic_payable'] ?? 'ESIC Payable', 'CR', $esicTotal);
        $push($ledgers['pt_payable'] ?? 'Professional Tax Payable', 'CR', $pt);
        $push($ledgers['tds_payable'] ?? 'TDS Payable', 'CR', $tds);
        $push($ledgers['loan_recovery'] ?? 'Staff Loans & Advances', 'CR', $loan);

        $totalDr = round(collect($entries)->where('type', 'DR')->sum('amount'), 2);
        $totalCr = round(collect($entries)->where('type', 'CR')->sum('amount'), 2);

        return GlBatch::updateOrCreate(
            ['pay_run_id' => $payRun->id],
            [
                'batch_code' => 'GL-'.$payRun->run_code,
                'voucher_date' => $payRun->period_end,
                'narration' => "Payroll Journal for {$payRun->run_code}",
                'entries_json' => $entries,
                'total_debit' => $totalDr,
                'total_credit' => $totalCr,
                'created_by' => $userId,
            ]
        );
    }

    public function toTallyXml(GlBatch $batch): string
    {
        $date = $batch->voucher_date->format('Ymd');
        $narration = htmlspecialchars((string) $batch->narration, ENT_XML1);

        $ledgerXml = '';
        foreach ($batch->entries_json as $entry) {
            $ledger = htmlspecialchars($entry['ledger'], ENT_XML1);
            $isDeemedPositive = $entry['type'] === 'DR' ? 'Yes' : 'No';
            $amount = $entry['type'] === 'DR'
                ? '-'.number_format((float) $entry['amount'], 2, '.', '')
                : number_format((float) $entry['amount'], 2, '.', '');

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
                        <NARRATION>{$narration}</NARRATION>
                        <VOUCHERTYPENAME>Journal</VOUCHERTYPENAME>
{$ledgerXml}                    </VOUCHER>
                </TALLYMESSAGE>
            </REQUESTDATA>
        </IMPORTDATA>
    </BODY>
</ENVELOPE>
XML;
    }
}
