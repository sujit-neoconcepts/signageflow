<?php

namespace SignageFlow\Hrms\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use SignageFlow\Hrms\Models\AttendanceAllowanceEntry;
use SignageFlow\Hrms\Models\AttendanceDay;
use SignageFlow\Hrms\Models\Claim;
use SignageFlow\Hrms\Models\LoanInstallment;
use SignageFlow\Hrms\Models\PayGroup;
use SignageFlow\Hrms\Models\PayRun;
use SignageFlow\Hrms\Models\PayRunItem;
use SignageFlow\Hrms\Models\SalaryRevision;
use SignageFlow\Hrms\Models\SalaryStructure;

class PayRunProcessingService
{
    public function __construct(
        protected StatutoryCalculationService $statutory,
        protected HrmsNotificationService $notifier
    ) {}

    public function createAndInitialize(array $payload): PayRun
    {
        $payGroup = PayGroup::findOrFail($payload['pay_group_id']);
        $start = Carbon::parse($payload['period_start'])->startOfDay();
        $end = Carbon::parse($payload['period_end'])->endOfDay();
        $fy = FinancialYearHelper::forDate($start);

        $runCode = sprintf(
            'PR-%s-%d%02d%s',
            $payGroup->code,
            $start->year,
            $start->month,
            ! empty($payload['week_number']) ? '-W'.$payload['week_number'] : '-'.strtoupper(substr(uniqid(), -4))
        );

        return DB::transaction(function () use ($payload, $payGroup, $start, $end, $fy, $runCode) {
            $payRun = PayRun::create([
                'run_code' => $runCode,
                'pay_group_id' => $payGroup->id,
                'pay_frequency' => $payGroup->pay_frequency,
                'financial_year' => $fy,
                'period_year' => $start->year,
                'period_month' => $start->month,
                'week_number' => $payload['week_number'] ?? null,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'sync_options' => $payload['sync_options'] ?? [
                    'absent' => true,
                    'leave' => true,
                    'late' => false,
                    'early_out' => false,
                    'ot' => true,
                    'break_hours' => false,
                ],
                'stage' => 'initialized',
            ]);

            $this->initializeItems($payRun);

            return $payRun->fresh('items');
        });
    }

    public function initialize(PayRun $payRun, ?array $syncOverride = null): PayRun
    {
        return $this->initializeItems($payRun, $syncOverride);
    }

    public function initializeItems(PayRun $payRun, ?array $syncOverride = null): PayRun
    {
        if ($payRun->stage !== 'initialized') {
            throw ValidationException::withMessages(['stage' => 'Only initialized PayRuns can be recalculated.']);
        }

        if ($syncOverride !== null) {
            $payRun->update(['sync_options' => $syncOverride]);
        }

        $sync = $payRun->sync_options ?? [];
        $start = $payRun->period_start->copy()->startOfDay();
        $end = $payRun->period_end->copy()->endOfDay();
        $totalDays = max(1, $start->diffInDays($end->copy()->startOfDay()) + 1);

        return DB::transaction(function () use ($payRun, $sync, $start, $end, $totalDays) {
            $structures = SalaryStructure::with(['employee.defaultShift', 'items.component'])
                ->where('pay_group_id', $payRun->pay_group_id)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $end->toDateString())
                ->get();

            foreach ($structures as $structure) {
                $emp = $structure->employee;
                if (! $emp) {
                    continue;
                }

                $runType = in_array($emp->employment_status, ['resigned', 'terminated'], true) ? 'fnf' : 'regular';

                $days = AttendanceDay::where('employee_id', $emp->id)
                    ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
                    ->get();

                $absentDays = ! empty($sync['absent']) ? $days->where('status', 'absent')->count() : 0;
                $unpaidLeaveDays = ! empty($sync['leave']) ? $days->where('status', 'on_leave')->where('leave_code', 'LOP')->count() : 0;
                $halfDays = (! empty($sync['absent']) || ! empty($sync['leave'])) ? ($days->where('status', 'half_day')->count() * 0.5) : 0;
                $lopDays = round($absentDays + $unpaidLeaveDays + $halfDays, 2);
                $payableDays = max(0, round($totalDays - $lopDays, 2));
                $ratio = $totalDays > 0 ? ($payableDays / $totalDays) : 1.0;

                $otMinutes = ! empty($sync['ot']) ? (int) $days->sum('approved_overtime_minutes') : 0;
                $otHours = round($otMinutes / 60, 2);
                $lateMinutes = ! empty($sync['late']) ? (int) $days->sum('late_minutes') : 0;
                $earlyOutMinutes = ! empty($sync['early_out']) ? (int) $days->sum('early_out_minutes') : 0;
                $breakMinutes = ! empty($sync['break_hours']) ? (int) $days->sum('break_minutes') : 0;

                // Prorate earnings & track statutory wage bases
                $earnings = [];
                $deductions = [];
                $pfWages = 0.0;
                $esicWages = 0.0;
                $ptWages = 0.0;
                $gross = 0.0;

                foreach ($structure->items as $item) {
                    $comp = $item->component;
                    if (! $comp || ! $comp->is_active) {
                        continue;
                    }
                    $prorated = round($item->amount * $ratio, 2);
                    if ($comp->type === 'earning') {
                        $earnings[$comp->code] = ['name' => $comp->name, 'amount' => $prorated];
                        $gross += $prorated;
                        if ($comp->is_pf_wage) {
                            $pfWages += $prorated;
                        }
                        if ($comp->is_esic_wage) {
                            $esicWages += $prorated;
                        }
                        if ($comp->is_pt_wage) {
                            $ptWages += $prorated;
                        }
                    } elseif ($comp->type === 'deduction') {
                        $deductions[$comp->code] = ['name' => $comp->name, 'amount' => (float) $item->amount];
                    }
                }

                // OT payout
                $otPay = 0.0;
                if ($otHours > 0) {
                    $hourlyRate = ($structure->monthly_gross / max(1, $totalDays)) / 8;
                    $otMult = (float) ($emp->defaultShift?->ot_multiplier ?? 1.5);
                    $otPay = round($hourlyRate * $otHours * $otMult, 2);
                    $earnings['OT_PAY'] = ['name' => 'Overtime Pay', 'amount' => $otPay];
                    $gross += $otPay;
                }

                // Attendance allowance
                $attAllowance = (float) AttendanceAllowanceEntry::where('employee_id', $emp->id)
                    ->where('period_year', $payRun->period_year)
                    ->where('period_month', $payRun->period_month)
                    ->where('status', 'approved')
                    ->sum('computed_amount');
                if ($attAllowance > 0) {
                    $earnings['ATT_ALLOW'] = ['name' => 'Attendance Allowance', 'amount' => $attAllowance];
                    $gross += $attAllowance;
                }

                // E-Claims reimbursement
                $claimsAmount = (float) Claim::where('employee_id', $emp->id)
                    ->where('approval_status', 'approved')
                    ->whereNull('pay_run_item_id')
                    ->sum('amount');
                if ($claimsAmount > 0) {
                    $earnings['E_CLAIM'] = ['name' => 'Reimbursements / E-Claims', 'amount' => $claimsAmount];
                    $gross += $claimsAmount;
                }

                // Arrear check (revision effective in this period)
                $arrearAmount = 0.0;
                $revision = SalaryRevision::where('employee_id', $emp->id)
                    ->where('arrear_applicable', true)
                    ->whereBetween('effective_from', [$start->toDateString(), $end->toDateString()])
                    ->latest('id')
                    ->first();
                if ($revision && $revision->new_ctc > $revision->previous_ctc) {
                    $arrearAmount = round($revision->new_ctc - $revision->previous_ctc, 2);
                }

                // Statutory calculations
                $stat = $this->statutory->calculate($emp, $payRun->financial_year, $payRun->period_month, $pfWages, $esicWages, $ptWages);

                // Loan EMI due
                $loanEmi = (float) LoanInstallment::whereHas('loan', fn ($q) => $q->where('employee_id', $emp->id)->where('status', 'active'))
                    ->where('status', 'pending')
                    ->whereDate('due_date', '<=', $end->toDateString())
                    ->sum('emi_amount');

                // Late / Early Out deduction if sync toggles enabled (pro-rata minute penalty)
                $lateDed = 0.0;
                if (($lateMinutes > 0 || $earlyOutMinutes > 0) && (! empty($sync['late']) || ! empty($sync['early_out']))) {
                    $perMinRate = ($structure->monthly_gross / max(1, $totalDays)) / 480;
                    $lateDed = round($perMinRate * ($lateMinutes + $earlyOutMinutes), 2);
                    if ($lateDed > 0) {
                        $deductions['LATE_EARLY'] = ['name' => 'Late / Early Out Deduction', 'amount' => $lateDed];
                    }
                }

                $componentDeductions = collect($deductions)->sum('amount');
                $totalDeductions = round(
                    $componentDeductions
                    + $stat['epf_employee']
                    + $stat['esic_employee']
                    + $stat['pt_amount']
                    + $stat['lwf_amount']
                    + $loanEmi,
                    2
                );

                $netWages = max(0, round($gross + $arrearAmount - $totalDeductions, 2));

                PayRunItem::updateOrCreate(
                    [
                        'pay_run_id' => $payRun->id,
                        'employee_id' => $emp->id,
                        'run_type' => $runType,
                    ],
                    [
                        'is_initialized' => true,
                        'payable_days' => $payableDays,
                        'lop_days' => $lopDays,
                        'ot_hours' => $otHours,
                        'late_minutes' => $lateMinutes,
                        'early_out_minutes' => $earlyOutMinutes,
                        'break_minutes' => $breakMinutes,
                        'attendance_summary' => [
                            'total_days' => $totalDays,
                            'payable_days' => $payableDays,
                            'lop_days' => $lopDays,
                            'ot_hours' => $otHours,
                            'late_minutes' => $lateMinutes,
                            'early_out_minutes' => $earlyOutMinutes,
                            'break_minutes' => $breakMinutes,
                        ],
                        'earnings_json' => $earnings,
                        'deductions_json' => $deductions,
                        'employer_contributions_json' => [
                            'EPF' => $stat['epf_employer'],
                            'ESIC' => $stat['esic_employer'],
                        ],
                        'gross_earnings' => round($gross, 2),
                        'epf_employee' => $stat['epf_employee'],
                        'epf_employer' => $stat['epf_employer'],
                        'esic_employee' => $stat['esic_employee'],
                        'esic_employer' => $stat['esic_employer'],
                        'pt_amount' => $stat['pt_amount'],
                        'lwf_amount' => $stat['lwf_amount'],
                        'loan_emi_amount' => $loanEmi,
                        'claim_reimbursement_amount' => $claimsAmount,
                        'attendance_allowance_amount' => $attAllowance,
                        'arrear_amount' => $arrearAmount,
                        'total_deductions' => $totalDeductions,
                        'net_wages' => $netWages,
                        'approval_status' => 'pending',
                    ]
                );
            }

            $this->refreshTotals($payRun);

            return $payRun->fresh('items');
        });
    }

    public function refreshTotals(PayRun $payRun): void
    {
        $items = $payRun->items()->where('is_initialized', true)->get();
        $payRun->update([
            'total_gross' => round($items->sum('gross_earnings'), 2),
            'total_deductions' => round($items->sum('total_deductions'), 2),
            'total_net' => round($items->sum('net_wages'), 2),
        ]);
    }

    public function submitForApproval(PayRun $payRun): PayRun
    {
        if ($payRun->stage !== 'initialized') {
            throw ValidationException::withMessages(['stage' => 'PayRun must be in initialized stage to submit.']);
        }
        $payRun->update(['stage' => 'pending_l1']);

        return $payRun->fresh();
    }

    public function approveStage(PayRun $payRun, int $userId): PayRun
    {
        return DB::transaction(function () use ($payRun, $userId) {
            if ($payRun->stage === 'pending_l1') {
                if ($payRun->payGroup?->l2_approver_user_id) {
                    $payRun->update([
                        'stage' => 'pending_l2',
                        'l1_approved_by' => $userId,
                        'l1_approved_at' => now(),
                    ]);

                    return $payRun->fresh();
                }

                $payRun->update([
                    'l1_approved_by' => $userId,
                    'l1_approved_at' => now(),
                ]);
                $this->finalizeAndLock($payRun, $userId);

                return $payRun->fresh();
            }

            if ($payRun->stage === 'pending_l2') {
                $this->finalizeAndLock($payRun, $userId);

                return $payRun->fresh();
            }

            throw ValidationException::withMessages(['stage' => 'PayRun is not awaiting approval.']);
        });
    }

    protected function finalizeAndLock(PayRun $payRun, int $userId): void
    {
        $payRun->update([
            'stage' => 'approved',
            'l2_approved_by' => $userId,
            'l2_approved_at' => now(),
        ]);

        $items = $payRun->items()->with('employee')->get();
        $empIds = $items->pluck('employee_id');

        // Lock attendance days for the period
        AttendanceDay::whereIn('employee_id', $empIds)
            ->whereBetween('attendance_date', [$payRun->period_start->toDateString(), $payRun->period_end->toDateString()])
            ->update(['is_locked_by_payroll' => true]);

        foreach ($items as $item) {
            $item->update(['approval_status' => 'approved']);

            // Mark due loan installments as deducted & decrement loan outstanding
            if ($item->loan_emi_amount > 0) {
                $dueInstallments = LoanInstallment::whereHas('loan', fn ($q) => $q->where('employee_id', $item->employee_id)->where('status', 'active'))
                    ->where('status', 'pending')
                    ->whereDate('due_date', '<=', $payRun->period_end->toDateString())
                    ->with('loan')
                    ->get();

                foreach ($dueInstallments as $inst) {
                    $inst->update([
                        'status' => 'deducted',
                        'pay_run_item_id' => $item->id,
                        'paid_at' => now(),
                    ]);
                    $loan = $inst->loan;
                    if ($loan) {
                        $remaining = max(0, round($loan->outstanding_amount - $inst->emi_amount, 2));
                        $loan->update([
                            'outstanding_amount' => $remaining,
                            'status' => $remaining <= 0 ? 'closed' : 'active',
                        ]);
                    }
                }
            }

            // Mark included E-Claims as paid
            if ($item->claim_reimbursement_amount > 0) {
                Claim::where('employee_id', $item->employee_id)
                    ->where('approval_status', 'approved')
                    ->whereNull('pay_run_item_id')
                    ->update(['approval_status' => 'paid', 'pay_run_item_id' => $item->id]);
            }

            // Mark attendance allowance entries as paid
            if ($item->attendance_allowance_amount > 0) {
                AttendanceAllowanceEntry::where('employee_id', $item->employee_id)
                    ->where('period_year', $payRun->period_year)
                    ->where('period_month', $payRun->period_month)
                    ->where('status', 'approved')
                    ->update(['status' => 'paid']);
            }
        }
    }

    public function publish(PayRun $payRun, int $userId): PayRun
    {
        if ($payRun->stage !== 'approved') {
            throw ValidationException::withMessages(['stage' => 'PayRun must be approved before publishing payslips.']);
        }

        $disk = config('hrms.storage_disk', 'local');

        foreach ($payRun->items()->with(['employee.department', 'employee.designation', 'payRun.payGroup'])->get() as $item) {
            $emp = $item->employee;
            $pdf = Pdf::loadView('hrms::pdf.payslip', ['item' => $item, 'payRun' => $payRun, 'employee' => $emp]);
            $path = "hrms/payslips/{$payRun->run_code}/{$emp->emp_code}.pdf";
            Storage::disk($disk)->put($path, $pdf->output());

            $item->update([
                'payslip_pdf_path' => $path,
                'is_published' => true,
            ]);

            $this->notifier->dispatch('payslip_published', $emp, [
                'period' => $payRun->period_start->format('M Y'),
                'net_wages' => number_format($item->net_wages, 2),
            ]);
        }

        $payRun->update([
            'stage' => 'published',
            'published_by' => $userId,
            'published_at' => now(),
        ]);

        return $payRun->fresh();
    }
}
