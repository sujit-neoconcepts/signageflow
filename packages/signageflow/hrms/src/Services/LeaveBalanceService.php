<?php

namespace SignageFlow\Hrms\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\LeaveBalance;
use SignageFlow\Hrms\Models\LeaveTransaction;
use SignageFlow\Hrms\Models\LeaveType;
use SignageFlow\Hrms\Models\LeaveYearEndRun;

class LeaveBalanceService
{
    public function getOrCreateBalance(Employee $employee, LeaveType $leaveType, string $financialYear): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'financial_year' => $financialYear,
            ],
            [
                'opening_balance' => 0,
                'accrued' => 0,
                'credited_adjustment' => 0,
                'taken' => 0,
                'pending' => 0,
                'lapsed' => 0,
                'encashed' => 0,
                'closing_balance' => 0,
            ]
        );
    }

    public function mutateBalance(
        Employee $employee,
        LeaveType $leaveType,
        string $financialYear,
        string $txnType,
        float $days,
        array $meta = []
    ): LeaveBalance {
        return DB::transaction(function () use ($employee, $leaveType, $financialYear, $txnType, $days, $meta) {
            $balance = $this->getOrCreateBalance($employee, $leaveType, $financialYear);
            $balance = LeaveBalance::whereKey($balance->id)->lockForUpdate()->firstOrFail();

            switch ($txnType) {
                case 'opening':
                    $balance->opening_balance += $days;
                    break;
                case 'accrual':
                case 'comp_off_credit':
                    $balance->accrued += $days;
                    break;
                case 'carry_forward':
                    $balance->opening_balance += $days;
                    break;
                case 'adjustment':
                    $balance->credited_adjustment += $days;
                    break;
                case 'debit':
                    if ($leaveType->is_paid && ($balance->closing_balance - $days) < 0 && empty($meta['allow_negative'])) {
                        throw ValidationException::withMessages([
                            'leave_type_id' => ["Insufficient {$leaveType->name} balance (Available: {$balance->closing_balance}, Requested: {$days})."],
                        ]);
                    }
                    $balance->taken += $days;
                    break;
                case 'reversal':
                    $balance->taken = max(0, $balance->taken - $days);
                    break;
                case 'lapse':
                    $balance->lapsed += $days;
                    break;
                case 'encashment':
                    $balance->encashed += $days;
                    break;
            }

            $balance->closing_balance = round(
                $balance->opening_balance
                + $balance->accrued
                + $balance->credited_adjustment
                - $balance->taken
                - $balance->lapsed
                - $balance->encashed,
                2
            );
            $balance->save();

            LeaveTransaction::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'financial_year' => $financialYear,
                'txn_type' => $txnType,
                'days' => $days,
                'balance_after' => $balance->closing_balance,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'remarks' => $meta['remarks'] ?? null,
                'created_by' => $meta['created_by'] ?? auth()->id(),
            ]);

            return $balance;
        });
    }

    public function adjustPending(Employee $employee, LeaveType $leaveType, string $financialYear, float $deltaDays): void
    {
        DB::transaction(function () use ($employee, $leaveType, $financialYear, $deltaDays) {
            $balance = $this->getOrCreateBalance($employee, $leaveType, $financialYear);
            $balance = LeaveBalance::whereKey($balance->id)->lockForUpdate()->firstOrFail();

            if ($deltaDays > 0 && $leaveType->is_paid && ($balance->closing_balance - $balance->pending) < $deltaDays) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["Insufficient available {$leaveType->name} balance (including pending requests)."],
                ]);
            }

            $balance->pending = max(0, round($balance->pending + $deltaDays, 2));
            $balance->save();
        });
    }

    public function recalculate(Employee $employee, LeaveType $leaveType, string $financialYear): LeaveBalance
    {
        return DB::transaction(function () use ($employee, $leaveType, $financialYear) {
            $balance = $this->getOrCreateBalance($employee, $leaveType, $financialYear);
            $balance = LeaveBalance::whereKey($balance->id)->lockForUpdate()->firstOrFail();

            $txns = LeaveTransaction::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('financial_year', $financialYear)
                ->get();

            $opening = $txns->whereIn('txn_type', ['opening', 'carry_forward'])->sum('days');
            $accrued = $txns->whereIn('txn_type', ['accrual', 'comp_off_credit'])->sum('days');
            $adj = $txns->where('txn_type', 'adjustment')->sum('days');
            $taken = $txns->where('txn_type', 'debit')->sum('days') - $txns->where('txn_type', 'reversal')->sum('days');
            $lapsed = $txns->where('txn_type', 'lapse')->sum('days');
            $encashed = $txns->where('txn_type', 'encashment')->sum('days');

            $balance->update([
                'opening_balance' => round($opening, 2),
                'accrued' => round($accrued, 2),
                'credited_adjustment' => round($adj, 2),
                'taken' => round(max(0, $taken), 2),
                'lapsed' => round($lapsed, 2),
                'encashed' => round($encashed, 2),
                'closing_balance' => round($opening + $accrued + $adj - max(0, $taken) - $lapsed - $encashed, 2),
            ]);

            return $balance->fresh();
        });
    }

    public function runAccrualForMonth(Carbon $date): int
    {
        $fy = FinancialYearHelper::forDate($date);
        $leaveTypes = LeaveType::where('is_active', true)
            ->where('accrual_frequency', '!=', 'none')
            ->get();

        $employees = Employee::active()->get();
        $count = 0;

        foreach ($leaveTypes as $type) {
            $increment = match ($type->accrual_frequency) {
                'monthly' => round($type->annual_quota / 12, 2),
                'quarterly' => in_array($date->month, [4, 7, 10, 1], true) ? round($type->annual_quota / 4, 2) : 0,
                'annual' => $date->month === (int) config('hrms.financial_year_start_month', 4) ? $type->annual_quota : 0,
                default => 0,
            };

            if ($increment <= 0) {
                continue;
            }

            foreach ($employees as $employee) {
                if ($type->gender_restriction !== 'all' && $employee->gender !== $type->gender_restriction) {
                    continue;
                }
                $this->mutateBalance($employee, $type, $fy, 'accrual', $increment, [
                    'remarks' => 'Scheduled accrual for '.$date->format('M Y'),
                ]);
                $count++;
            }
        }

        return $count;
    }

    public function runYearEnd(string $fromFinancialYear, ?int $userId = null): LeaveYearEndRun
    {
        $toFinancialYear = FinancialYearHelper::next($fromFinancialYear);

        return DB::transaction(function () use ($fromFinancialYear, $toFinancialYear, $userId) {
            $balances = LeaveBalance::where('financial_year', $fromFinancialYear)
                ->with(['employee', 'leaveType'])
                ->get();

            $processedEmployees = [];
            $totalCf = 0;
            $totalLapsed = 0;

            foreach ($balances as $bal) {
                if (! $bal->employee || ! $bal->leaveType || $bal->closing_balance <= 0) {
                    continue;
                }

                $available = max(0, $bal->closing_balance);
                $cf = min($available, (float) $bal->leaveType->max_carry_forward);
                $lapsed = round($available - $cf, 2);

                if ($lapsed > 0) {
                    $this->mutateBalance($bal->employee, $bal->leaveType, $fromFinancialYear, 'lapse', $lapsed, [
                        'remarks' => "FY {$fromFinancialYear} year-end lapse",
                        'created_by' => $userId,
                    ]);
                    $totalLapsed += $lapsed;
                }

                if ($cf > 0) {
                    $this->mutateBalance($bal->employee, $bal->leaveType, $toFinancialYear, 'carry_forward', $cf, [
                        'remarks' => "Carried forward from FY {$fromFinancialYear}",
                        'created_by' => $userId,
                    ]);
                    $totalCf += $cf;
                }

                $processedEmployees[$bal->employee_id] = true;
            }

            return LeaveYearEndRun::create([
                'from_financial_year' => $fromFinancialYear,
                'to_financial_year' => $toFinancialYear,
                'employees_processed' => count($processedEmployees),
                'total_carried_forward' => round($totalCf, 2),
                'total_lapsed' => round($totalLapsed, 2),
                'run_by' => $userId ?? auth()->id(),
            ]);
        });
    }
}
