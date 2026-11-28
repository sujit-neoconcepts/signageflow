<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Leave;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SignageFlow\Hrms\Models\CompOff;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\LeaveBalance;
use SignageFlow\Hrms\Models\LeaveRequest;
use SignageFlow\Hrms\Models\LeaveType;
use SignageFlow\Hrms\Models\LeaveYearEndRun;
use SignageFlow\Hrms\Services\FinancialYearHelper;
use SignageFlow\Hrms\Services\LeaveAttendanceSyncService;
use SignageFlow\Hrms\Services\LeaveBalanceService;

class LeaveActionController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsLeave_applyOnBehalf')->only(['index', 'applyOnBehalf', 'batchApply', 'addCompOff', 'activateStatutory']);
        $this->middleware('can:hrmsLeave_adjust')->only(['adjustBalance', 'recalculateBalance']);
        $this->middleware('can:hrmsLeave_import')->only(['importLeave']);
        $this->middleware('can:hrmsLeave_yearEnd')->only(['runYearEnd']);
    }

    public function index()
    {
        $fy = FinancialYearHelper::current();

        return Inertia::render('Hrms/Leave/AdminActions', [
            'currentFy' => $fy,
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'emp_code', 'full_name', 'gender']),
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('name')->get(),
            'balances' => LeaveBalance::with(['employee:id,emp_code,full_name', 'leaveType:id,code,name'])
                ->where('financial_year', $fy)
                ->latest('updated_at')
                ->take(100)
                ->get(),
            'yearEndRuns' => LeaveYearEndRun::latest()->take(10)->get(),
        ]);
    }

    public function applyOnBehalf(
        Request $request,
        LeaveBalanceService $balanceService,
        LeaveAttendanceSyncService $syncService
    ) {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'leave_type_id' => 'required|exists:hrms_leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'day_mode' => 'required|in:full,first_half,second_half',
            'apply_mode' => 'required|in:manager,super_admin',
            'auto_approve' => 'boolean',
            'reason' => 'required|string|max:500',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $fy = FinancialYearHelper::forDate($data['start_date']);

        $days = $data['day_mode'] === 'full'
            ? (Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1)
            : 0.5;

        DB::transaction(function () use ($data, $employee, $leaveType, $fy, $days, $balanceService, $syncService) {
            $autoApprove = (bool) ($data['auto_approve'] ?? false);

            $req = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'financial_year' => $fy,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'day_mode' => $data['day_mode'],
                'total_days' => $days,
                'reason' => $data['reason'],
                'applied_by_user_id' => auth()->id(),
                'apply_mode' => $data['apply_mode'],
                'approval_status' => $autoApprove ? 'approved' : 'pending_l1',
                'current_level' => 1,
                'current_approver_id' => $autoApprove ? null : $employee->l1_approver_id,
                'l1_approver_id' => $employee->l1_approver_id,
                'l2_approver_id' => $employee->l2_approver_id,
            ]);

            if ($autoApprove) {
                $balanceService->mutateBalance($employee, $leaveType, $fy, 'debit', $days, [
                    'reference_type' => LeaveRequest::class,
                    'reference_id' => $req->id,
                    'remarks' => 'Applied on behalf & auto-approved',
                    'allow_negative' => $data['apply_mode'] === 'super_admin',
                ]);
                $syncService->syncApprovedLeave($req->fresh(['employee', 'leaveType']));
            } else {
                $balanceService->adjustPending($employee, $leaveType, $fy, $days);
            }
        });

        return back()->with(['message' => 'Leave request recorded on behalf of employee.', 'msg_type' => 'success']);
    }

    public function batchApply(Request $request, LeaveBalanceService $balanceService, LeaveAttendanceSyncService $syncService)
    {
        $data = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:hrms_employees,id',
            'leave_type_id' => 'required|exists:hrms_leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $fy = FinancialYearHelper::forDate($data['start_date']);
        $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;

        DB::transaction(function () use ($data, $leaveType, $fy, $days, $balanceService, $syncService) {
            foreach (Employee::whereIn('id', $data['employee_ids'])->get() as $emp) {
                $req = LeaveRequest::create([
                    'employee_id' => $emp->id,
                    'leave_type_id' => $leaveType->id,
                    'financial_year' => $fy,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'day_mode' => 'full',
                    'total_days' => $days,
                    'reason' => $data['reason'],
                    'applied_by_user_id' => auth()->id(),
                    'apply_mode' => 'batch',
                    'approval_status' => 'approved',
                ]);

                $balanceService->mutateBalance($emp, $leaveType, $fy, 'debit', $days, [
                    'reference_type' => LeaveRequest::class,
                    'reference_id' => $req->id,
                    'remarks' => 'Batch leave entry',
                    'allow_negative' => true,
                ]);
                $syncService->syncApprovedLeave($req->fresh(['employee', 'leaveType']));
            }
        });

        return back()->with(['message' => 'Batch leave applied for '.count($data['employee_ids']).' employees.', 'msg_type' => 'success']);
    }

    public function addCompOff(Request $request, LeaveBalanceService $balanceService)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'worked_date' => 'required|date',
            'days_earned' => 'required|numeric|in:0.5,1,1.5,2',
            'expires_at' => 'nullable|date|after_or_equal:worked_date',
            'remarks' => 'nullable|string|max:255',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $coType = LeaveType::where('code', 'CO')->firstOrFail();
        $fy = FinancialYearHelper::forDate($data['worked_date']);

        DB::transaction(function () use ($data, $employee, $coType, $fy, $balanceService) {
            $co = CompOff::create($data + ['financial_year' => $fy, 'status' => 'approved', 'created_by' => auth()->id()]);
            $balanceService->mutateBalance($employee, $coType, $fy, 'comp_off_credit', (float) $data['days_earned'], [
                'reference_type' => CompOff::class,
                'reference_id' => $co->id,
                'remarks' => $data['remarks'] ?? 'Comp-Off credited for '.$data['worked_date'],
            ]);
        });

        return back()->with(['message' => 'Comp-Off credited.', 'msg_type' => 'success']);
    }

    public function activateStatutory(Request $request, LeaveBalanceService $balanceService)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'code' => 'required|in:ML,PL',
            'days' => 'nullable|numeric|min:1|max:365',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $leaveType = LeaveType::where('code', $data['code'])->firstOrFail();
        $fy = FinancialYearHelper::current();
        $quota = (float) ($data['days'] ?? $leaveType->annual_quota);

        $balanceService->mutateBalance($employee, $leaveType, $fy, 'opening', $quota, [
            'remarks' => "Activated {$leaveType->name} ({$quota} days)",
        ]);

        return back()->with(['message' => "{$leaveType->name} activated for {$employee->full_name}.", 'msg_type' => 'success']);
    }

    public function adjustBalance(Request $request, LeaveBalanceService $balanceService)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'leave_type_id' => 'required|exists:hrms_leave_types,id',
            'financial_year' => 'required|string|max:15',
            'days' => 'required|numeric|not_in:0',
            'remarks' => 'required|string|max:255',
        ]);

        $balanceService->mutateBalance(
            Employee::findOrFail($data['employee_id']),
            LeaveType::findOrFail($data['leave_type_id']),
            $data['financial_year'],
            'adjustment',
            (float) $data['days'],
            ['remarks' => $data['remarks']]
        );

        return back()->with(['message' => 'Leave balance adjusted.', 'msg_type' => 'success']);
    }

    public function recalculateBalance(Request $request, LeaveBalanceService $balanceService)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'leave_type_id' => 'required|exists:hrms_leave_types,id',
            'financial_year' => 'required|string|max:15',
        ]);

        $balanceService->recalculate(
            Employee::findOrFail($data['employee_id']),
            LeaveType::findOrFail($data['leave_type_id']),
            $data['financial_year']
        );

        return back()->with(['message' => 'Leave balance recalculated from transaction ledger.', 'msg_type' => 'success']);
    }

    public function importLeave(Request $request, LeaveBalanceService $balanceService)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);
        $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, true);

        $count = 0;
        DB::transaction(function () use ($rows, $balanceService, &$count) {
            foreach ($rows as $idx => $row) {
                if ($idx === 1 || empty(trim((string) ($row['A'] ?? '')))) {
                    continue;
                }
                $emp = Employee::where('emp_code', trim((string) $row['A']))->first();
                $type = LeaveType::where('code', trim((string) ($row['B'] ?? '')))->first();
                $fy = trim((string) ($row['C'] ?? '')) ?: FinancialYearHelper::current();
                $days = (float) ($row['D'] ?? 0);

                if ($emp && $type && $days != 0.0) {
                    $balanceService->mutateBalance($emp, $type, $fy, 'opening', $days, ['remarks' => 'Excel Leave Import']);
                    $count++;
                }
            }
        });

        return back()->with(['message' => "Imported {$count} leave balance rows.", 'msg_type' => 'success']);
    }

    public function runYearEnd(Request $request, LeaveBalanceService $balanceService)
    {
        $data = $request->validate(['from_financial_year' => 'required|string|max:15']);
        $run = $balanceService->runYearEnd($data['from_financial_year'], auth()->id());

        return back()->with([
            'message' => "FY {$run->from_financial_year} -> {$run->to_financial_year} year-end complete ({$run->employees_processed} employees, CF: {$run->total_carried_forward}, Lapsed: {$run->total_lapsed}).",
            'msg_type' => 'success',
        ]);
    }
}
