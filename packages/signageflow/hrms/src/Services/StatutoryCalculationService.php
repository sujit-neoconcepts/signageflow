<?php

namespace SignageFlow\Hrms\Services;

use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\StatutoryConfig;

class StatutoryCalculationService
{
    public function calculate(
        Employee $employee,
        string $financialYear,
        int $periodMonth,
        float $pfWages,
        float $esicWages,
        float $ptWages
    ): array {
        $config = StatutoryConfig::where('financial_year', $financialYear)->where('is_active', true)->first()
            ?? StatutoryConfig::where('is_active', true)->latest('id')->first();

        if (! $config) {
            return [
                'epf_employee' => 0.0,
                'epf_employer' => 0.0,
                'esic_employee' => 0.0,
                'esic_employer' => 0.0,
                'pt_amount' => 0.0,
                'lwf_amount' => 0.0,
            ];
        }

        $epfEmployee = 0.0;
        $epfEmployer = 0.0;
        if ($employee->epf_enabled && $pfWages > 0) {
            $epfBase = min($pfWages, (float) $config->epf_wage_ceiling);
            $epfEmployee = round($epfBase * ($config->epf_employee_rate / 100));
            $epfEmployer = round($epfBase * ($config->epf_employer_rate / 100));
        }

        $esicEmployee = 0.0;
        $esicEmployer = 0.0;
        if ($employee->esic_enabled && $esicWages > 0 && $esicWages <= (float) $config->esic_wage_ceiling) {
            $esicEmployee = (float) ceil($esicWages * ($config->esic_employee_rate / 100));
            $esicEmployer = round($esicWages * ($config->esic_employer_rate / 100), 2);
        }

        $ptAmount = 0.0;
        if ($employee->pt_enabled && $ptWages > 0) {
            $state = strtoupper($employee->pt_state_code ?: 'MH');
            $slabs = $config->pt_state_slabs[$state] ?? [];
            foreach ($slabs as $slab) {
                if ($ptWages >= ($slab['min'] ?? 0) && $ptWages <= ($slab['max'] ?? 9999999)) {
                    $ptAmount = ($periodMonth === 2 && isset($slab['feb_tax']))
                        ? (float) $slab['feb_tax']
                        : (float) ($slab['tax'] ?? $slab['amount'] ?? 0);
                    break;
                }
            }
        }

        $lwfAmount = 0.0;
        if ($employee->lwf_enabled) {
            $state = strtoupper($employee->pt_state_code ?: 'MH');
            $rule = $config->lwf_rules[$state] ?? null;
            if ($rule && in_array($periodMonth, $rule['months'] ?? [], true)) {
                $lwfAmount = (float) ($rule['employee'] ?? 0);
            }
        }

        return [
            'epf_employee' => $epfEmployee,
            'epf_employer' => $epfEmployer,
            'esic_employee' => $esicEmployee,
            'esic_employer' => $esicEmployer,
            'pt_amount' => $ptAmount,
            'lwf_amount' => $lwfAmount,
        ];
    }
}
