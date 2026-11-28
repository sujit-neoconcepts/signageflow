<?php

namespace SignageFlow\Hrms\Http\Controllers\Mobile;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\FwaPolicy;
use SignageFlow\Hrms\Models\FwaRequest;
use SignageFlow\Hrms\Models\Holiday;
use SignageFlow\Hrms\Models\LeaveBalance;
use SignageFlow\Hrms\Models\LeaveRequest;
use SignageFlow\Hrms\Models\LeaveType;
use SignageFlow\Hrms\Services\ApprovalRoutingService;
use SignageFlow\Hrms\Services\FinancialYearHelper;
use SignageFlow\Hrms\Services\HrmsNotificationService;
use SignageFlow\Hrms\Services\LeaveBalanceService;

class LeaveMobileController extends Controller
{
    public function summary(Request $request, LeaveBalanceService $balanceService)
    {
        $employee = $this->resolveEmployee($request);
        $fy = FinancialYearHelper::current();

        $leaveTypes = LeaveType::where('is_active', true)->orderBy('code')->get();
        foreach ($leaveTypes as $lt) {
            if ($lt->gender_restriction !== 'all' && $employee->gender && $employee->gender !== $lt->gender_restriction) {
                continue;
            }
            $balanceService->getOrCreateBalance($employee, $lt, $fy);
        }

        $balances = LeaveBalance::with('leaveType:id,code,name,is_paid,allow_half_day,requires_attachment')
            ->where('employee_id', $employee->id)
            ->where('financial_year', $fy)
            ->get()
            ->map(fn ($b) => [
                'leave_type_id' => $b->leave_type_id,
                'code' => $b->leaveType?->code,
                'name' => $b->leaveType?->name,
                'is_paid' => $b->leaveType?->is_paid,
                'allow_half_day' => $b->leaveType?->allow_half_day,
                'opening' => $b->opening_balance,
                'accrued' => $b->accrued,
                'taken' => $b->taken,
                'pending' => $b->pending,
                'available' => round(max(0, $b->closing_balance - $b->pending), 2),
            ]);

        $holidays = Holiday::where('financial_year', $fy)
            ->where('is_active', true)
            ->orderBy('start_date')
            ->get(['id', 'name', 'start_date', 'end_date', 'holiday_type']);

        $fwaPolicies = FwaPolicy::where('is_active', true)->get(['id', 'code', 'name', 'mode']);

        return response()->json([
            'financial_year' => $fy,
            'balances' => $balances,
            'holidays' => $holidays,
            'fwa_policies' => $fwaPolicies,
        ]);
    }

    public function requests(Request $request)
    {
        $employee = $this->resolveEmployee($request);
        $rows = LeaveRequest::with('leaveType:id,code,name')
            ->where('employee_id', $employee->id)
            ->latest()
            ->take(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'leave_type' => $r->leaveType?->name,
                'code' => $r->leaveType?->code,
                'start_date' => $r->start_date->format('Y-m-d'),
                'end_date' => $r->end_date->format('Y-m-d'),
                'day_mode' => $r->day_mode,
                'total_days' => $r->total_days,
                'reason' => $r->reason,
                'approval_status' => $r->approval_status,
            ]);

        $fwa = FwaRequest::with('policy:id,code,name')
            ->where('employee_id', $employee->id)
            ->latest()
            ->take(20)
            ->get();

        return response()->json(['requests' => $rows, 'fwa_requests' => $fwa]);
    }

    public function store(
        Request $request,
        ApprovalRoutingService $routing,
        LeaveBalanceService $balanceService,
        HrmsNotificationService $notifier
    ) {
        $employee = $this->resolveEmployee($request);

        $data = $request->validate([
            'leave_type_id' => 'required|exists:hrms_leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'day_mode' => 'required|in:full,first_half,second_half',
            'reason' => 'required|string|max:500',
            'attachment' => 'nullable|file|max:5120',
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        if ($data['day_mode'] !== 'full' && ! $leaveType->allow_half_day) {
            return response()->json(['message' => 'Half-day is not allowed for this leave type.'], 422);
        }

        // Check blocked annual leave dates
        $blocked = Holiday::where('holiday_type', 'blocked_annual_leave')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($blocked) {
            return response()->json(['message' => 'Selected dates fall inside a Blocked Annual Leave period.'], 422);
        }

        $days = $data['day_mode'] === 'full'
            ? (Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1)
            : 0.5;

        $fy = FinancialYearHelper::forDate($data['start_date']);
        $attachmentPath = $request->hasFile('attachment')
            ? $request->file('attachment')->store('hrms/leave_attachments/'.$employee->id, config('hrms.storage_disk', 'local'))
            : null;

        $l1 = $routing->resolveApprover($employee, 1, 'leave');

        $leaveRequest = DB::transaction(function () use ($employee, $leaveType, $fy, $data, $days, $attachmentPath, $l1, $balanceService) {
            $balanceService->adjustPending($employee, $leaveType, $fy, $days);

            return LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'financial_year' => $fy,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'day_mode' => $data['day_mode'],
                'total_days' => $days,
                'reason' => $data['reason'],
                'attachment_path' => $attachmentPath,
                'applied_by_user_id' => auth()->id(),
                'apply_mode' => 'self',
                'approval_status' => 'pending_l1',
                'current_level' => 1,
                'current_approver_id' => $l1['approver']?->id,
                'l1_approver_id' => $l1['approver']?->id,
                'l2_approver_id' => $employee->l2_approver_id,
            ]);
        });

        if ($l1['approver']) {
            $notifier->dispatch('leave_applied', $l1['approver'], [
                'employee_name' => $employee->full_name,
                'leave_type' => $leaveType->name,
                'days' => $days,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]);
        }

        return response()->json(['message' => 'Leave request submitted.', 'request_id' => $leaveRequest->id], 201);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest, LeaveBalanceService $balanceService)
    {
        $employee = $this->resolveEmployee($request);
        if ($leaveRequest->employee_id !== $employee->id) {
            abort(403);
        }

        if (! in_array($leaveRequest->approval_status, ['pending_l1', 'pending_l2'], true)) {
            return response()->json(['message' => 'Only pending requests can be cancelled.'], 422);
        }

        DB::transaction(function () use ($leaveRequest, $employee, $balanceService) {
            $balanceService->adjustPending($employee, $leaveRequest->leaveType, $leaveRequest->financial_year, -$leaveRequest->total_days);
            $leaveRequest->update(['approval_status' => 'cancelled']);
        });

        return response()->json(['message' => 'Leave request cancelled.']);
    }

    public function storeFwa(Request $request, ApprovalRoutingService $routing)
    {
        $employee = $this->resolveEmployee($request);
        $data = $request->validate([
            'fwa_policy_id' => 'required|exists:hrms_fwa_policies,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
        ]);

        $l1 = $routing->resolveApprover($employee, 1, 'leave');
        $fwa = FwaRequest::create($data + [
            'employee_id' => $employee->id,
            'financial_year' => FinancialYearHelper::forDate($data['start_date']),
            'approval_status' => 'pending_l1',
            'current_approver_id' => $l1['approver']?->id,
        ]);

        return response()->json(['message' => 'FWA request submitted.', 'fwa_id' => $fwa->id], 201);
    }

    protected function resolveEmployee(Request $request): Employee
    {
        $employee = Employee::active()->where('user_id', $request->user()->id)->first();
        if (! $employee) {
            abort(403, 'No active HRMS employee profile linked to this user.');
        }

        return $employee;
    }
}
