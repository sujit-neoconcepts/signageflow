<?php

namespace SignageFlow\Hrms\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SignageFlow\Hrms\Models\BankTemplate;
use SignageFlow\Hrms\Models\PayRun;

class BankSubmissionService
{
    public function buildSpreadsheet(PayRun $payRun, ?BankTemplate $template = null): array
    {
        $template = $template ?? $payRun->payGroup?->defaultBankTemplate
            ?? BankTemplate::where('is_default', true)->first();

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

        $rowNum = 2;
        foreach ($payRun->items()->with('employee')->where('net_wages', '>', 0)->get() as $item) {
            $emp = $item->employee;
            $row = [];
            foreach ($mapping as $field) {
                $row[] = match ($field) {
                    'emp_code' => $emp?->emp_code,
                    'full_name' => $emp?->full_name,
                    'bank_name' => $emp?->bank_name,
                    'bank_account_no' => $emp?->bank_account_no,
                    'bank_ifsc' => $emp?->bank_ifsc,
                    'net_wages' => round($item->net_wages, 2),
                    'run_code' => $payRun->run_code,
                    default => '',
                };
            }
            $sheet->fromArray($row, null, 'A'.$rowNum++);
        }

        $format = $template?->format ?? 'xlsx';
        $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);

        return ['writer' => $writer, 'extension' => $format];
    }
}
