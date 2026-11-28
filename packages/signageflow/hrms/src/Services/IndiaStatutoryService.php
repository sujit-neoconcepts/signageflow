<?php

namespace SignageFlow\Hrms\Services;

use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\StatutoryConfig;

class IndiaStatutoryService
{
    public function calculate(
        Employee $employee,
        float $pfWages,
        float $esicWages,
        float $ptWages,
        int $periodMonth,
        string $financialYear
    ): array {
        $config = StatutoryConfig::where('financial_year', $financialYear)->where('is_active', true)->first()
            ?? StatutoryConfig::where('is_active', true)->latest('id')->first();

        $epfEmp = 0.0;
        $epfEr = 0.0;
        $esicEmp = 0.0;
        $esicEr = 0.0;
        $pt = 0.0;
        $lwf = 0.0;

        if (! $config) {
            return compact('epfEmp', 'epfEr', 'esicEmp', 'esicEr', 'pt', 'lwf');
        }

        if ($employee->epf_enabled && $pfWages > 0) {
            $cappedPf = min($pfWages, (float) $config->epf_wage_ceiling);
            $epfEmp = round($cappedPf * ((float) $config->epf_employee_rate / 100), 0);
            $epfEr = round($cappedPf * ((float) $config->epf_employer_rate / 100), 0);
        }

        if ($employee->esic_enabled && $esicWages > 0 && $esicWages <= (float) $config->esic_wage_ceiling) {
            $esicEmp = (float) ceil($esicWages * ((float) $config->esic_employee_rate / 100));
            $esicEr = (float) ceil($esicWages * ((float) $config->esic_employer_rate / 100));
        }

        if ($employee->pt_enabled && $ptWages > 0) {
            $state = strtoupper($employee->pt_state_code ?: 'MH');
            $slabs = $config->pt_state_slabs[$state] ?? [];
            foreach ($slabs as $slab) {
                if ($ptWages >= (float) $slab['min'] && $ptWages <= (float) $slab['max']) {
                    $pt = ($periodMonth === 2 && isset($slab['feb_tax']))
                        ? (float) $slab['feb_tax']
                        : (float) $slab['tax'];
                    break;
                }
            }
        }

        if ($employee->lwf_enabled) {
            $state = strtoupper($employee->pt_state_code ?: 'MH');
            $lwfRule = $config->lwf_rules[$state] ?? null;
            if ($lwfRule && in_array($periodMonth, $lwfRule['months'] ?? [], true)) {
                $lwf = (float) ($lwfRule['employee'] ?? 0);
            }
        }

        return [
            'epfEmp' => $epfEmp,
            'epfEr' => $epfEr,
            'esicEmp' => $esicEmp,
            'esicEr' => $esicEr,
            'pt' => $pt,
            'lwf' => $lwf,
        ];
    }
}
