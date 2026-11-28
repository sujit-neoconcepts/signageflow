<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Leave;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\ApprovalDelegation;
use SignageFlow\Hrms\Models\Category;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\FwaRequest;
use SignageFlow\Hrms\Models\LeaveRequest;
use SignageFlow\Hrms\Models\LeaveType;
use SignageFlow\Hrms\Services\ApprovalRoutingService;
use SignageFlow\Hrms\Services\HrmsNotificationService;
use SignageFlow\Hrms\Services\LeaveAttendanceSyncService;
use SignageFlow\Hrms\Services\LeaveBalanceService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class LeaveApprovalController extends Controller
{
    protected array $resourceNeo = [
        'resourceName' => 'hrms.leave.approvals',
        'resourceTitle' => 'Leave Approvals',
        'actions' => ['r', 'u'],
    ];

    public function __construct()
    {
        $this->middleware('can:hrmsLeave_list')->only(['index']);
        $this->middleware('can:hrmsLeave_approve')->only(['act', 'actFwa']);
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'waiting'); // waiting | acted | previous_level | backup_officer
        $perPage = in_array((int) $request->input('perPage', 25), [5, 10, 25, 50, 100], true)
            ? (int) $request->input('perPage', 25)
            : 25;

        $actorEmployee = Employee::where('user_id', auth()->id())->first();
        $isSuperAdmin = auth()->user()->hasRole(env('APP_SUPER_ADMIN', 'super-admin'));

        $query = LeaveRequest::query()
            ->leftJoin('hrms_employees as emp', 'hrms_leave_requests.employee_id', '=', 'emp.id')
            ->leftJoin('hrms_leave_types as lt', 'hrms_leave_requests.leave_type_id', '=', 'lt.id')
            ->select([
                'hrms_leave_requests.*',
                'emp.emp_code as emp_code',
                'emp.full_name as employee_name',
                'emp.category_id as emp_category_id',
                'lt.code as leave_type_code',
                'lt.name as leave_type_name',
            ]);

        if ($tab === 'waiting') {
            $query->whereIn('hrms_leave_requests.approval_status', ['pending_l1', 'pending_l2']);
            if (! $isSuperAdmin && $actorEmployee) {
                $query->where('hrms_leave_requests.current_approver_id', $actorEmployee->id);
            }
        } elseif ($tab === 'acted') {
            $query->whereIn('hrms_leave_requests.approval_status', ['approved', 'rejected']);
            if (! $isSuperAdmin && $actorEmployee) {
                $query->where(fn ($q) => $q->where('hrms_leave_requests.l1_approver_id', $actorEmployee->id)->orWhere('hrms_leave_requests.l2_approver_id', $actorEmployee->id));
            }
        } elseif ($tab === 'previous_level') {
            $query->where('hrms_leave_requests.approval_status', 'pending_l1');
            if (! $isSuperAdmin && $actorEmployee) {
                $query->where('emp.l2_approver_id', $actorEmployee->id);
            }
        } elseif ($tab === 'backup_officer') {
            $query->whereIn('hrms_leave_requests.approval_status', ['pending_l1', 'pending_l2']);
            if (! $isSuperAdmin && $actorEmployee) {
                $delegatorIds = ApprovalDelegation::where('backup_employee_id', $actorEmployee->id)
                    ->where('is_active', true)
                    ->whereIn('module', ['all', 'leave'])
                    ->pluck('delegator_employee_id');
                $backupForIds = Employee::where('backup_approver_id', $actorEmployee->id)->pluck('id');
                $query->where(fn ($q) => $q->whereIn('hrms_leave_requests.current_approver_id', $delegatorIds)->orWhereIn('hrms_leave_requests.employee_id', $backupForIds));
            }
        }

        $query->when($request->filled('start_date'), fn ($q) => $q->whereDate('hrms_leave_requests.end_date', '>=', $request->input('start_date')))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('hrms_leave_requests.start_date', '<=', $request->input('end_date')));

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query->orWhere('emp.emp_code', 'LIKE', "%{$value}%")
                        ->orWhere('emp.full_name', 'LIKE', "%{$value}%")
                        ->orWhere('lt.name', 'LIKE', "%{$value}%")
                        ->orWhere('hrms_leave_requests.reason', 'LIKE', "%{$value}%");
                });
            });
        });

        $requests = QueryBuilder::for($query)
            ->defaultSort('-hrms_leave_requests.created_at')
            ->allowedSorts([
                AllowedSort::field('employee_name', 'emp.full_name'),
                AllowedSort::field('leave_type_code', 'lt.code'),
                'start_date',
                'total_days',
                'approval_status',
            ])
            ->allowedFilters([
                AllowedFilter::exact('leave_type_id', 'hrms_leave_requests.leave_type_id'),
                AllowedFilter::exact('category_id', 'emp.category_id'),
                AllowedFilter::exact('approval_status', 'hrms_leave_requests.approval_status'),
                $globalSearch,
            ])
            ->paginate($perPage)
            ->withQueryString();

        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray();
        $categories = Category::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray();

        $fwaRequests = FwaRequest::with(['employee:id,emp_code,full_name', 'policy:id,code,name'])
            ->latest()
            ->take(30)
            ->get();

        return Inertia::render('Hrms/Leave/Approvals', [
            'tab' => $tab,
            'filters' => $request->only(['start_date', 'end_date']),
            'leaveRequests' => $requests,
            'fwaRequests' => $fwaRequests,
            'resourceNeo' => $this->resourceNeo,
        ])->table(function (InertiaTable $table) use ($leaveTypes, $categories) {
            $table->withGlobalSearch()
                ->column('emp_code', 'Emp Code', searchable: true, sortable: false)
                ->column('employee_name', 'Employee', searchable: true, sortable: true)
                ->column('leave_type_code', 'Leave Type', searchable: false, sortable: true)
                ->column('start_date', 'Start Date', searchable: false, sortable: true)
                ->column('end_date', 'End Date', searchable: false, sortable: false)
                ->column('total_days', 'Days', searchable: false, sortable: true)
                ->column('reason', 'Reason', searchable: true, sortable: false)
                ->column('approval_status', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->selectFilter(key: 'leave_type_id', label: 'Leave Type', options: $leaveTypes, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'category_id', label: 'Category', options: $categories, noFilterOptionLabel: 'All')
                ->perPageOptions([5, 10, 25, 50, 100]);
        });
    }

    public function act(
        Request $request,
        LeaveRequest $leaveRequest,
        ApprovalRoutingService $routing,
        LeaveBalanceService $balanceService,
        LeaveAttendanceSyncService $syncService,
        HrmsNotificationService $notifier
    ) {
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'remarks' => 'nullable|string|max:500',
        ]);

        $authCheck = $routing->canActOn(auth()->user(), $leaveRequest->currentApprover, $leaveRequest->employee, 'leave');
        if (! $authCheck['authorized']) {
            abort(403, 'Not authorized to approve/reject this leave request.');
        }

        if (! in_array($leaveRequest->approval_status, ['pending_l1', 'pending_l2'], true)) {
            return back()->with(['message' => 'This leave request is already finalized.', 'msg_type' => 'warning']);
        }

        DB::transaction(function () use ($data, $leaveRequest, $authCheck, $routing, $balanceService, $syncService, $notifier) {
            $emp = $leaveRequest->employee;
            $type = $leaveRequest->leaveType;
            $fy = $leaveRequest->financial_year;

            if ($data['action'] === 'reject') {
                $balanceService->adjustPending($emp, $type, $fy, -$leaveRequest->total_days);
                $leaveRequest->update([
                    'approval_status' => 'rejected',
                    'remarks' => $data['remarks'] ?? 'Rejected',
                    'acted_via_backup' => $authCheck['via_backup'],
                    $leaveRequest->current_level === 1 ? 'l1_status' : 'l2_status' => 'rejected',
                    $leaveRequest->current_level === 1 ? 'l1_acted_at' : 'l2_acted_at' => now(),
                ]);

                $notifier->dispatch('leave_status_changed', $emp, [
                    'leave_type' => $type->name,
                    'start_date' => $leaveRequest->start_date->format('Y-m-d'),
                    'end_date' => $leaveRequest->end_date->format('Y-m-d'),
                    'status' => 'Rejected',
                ]);

                return;
            }

            if ($leaveRequest->current_level === 1 && $emp->l2Approver) {
                $next = $routing->resolveApprover($emp, 2, 'leave');
                $leaveRequest->update([
                    'approval_status' => 'pending_l2',
                    'current_level' => 2,
                    'current_approver_id' => $next['approver']?->id,
                    'l1_status' => 'approved',
                    'l1_acted_at' => now(),
                    'acted_via_backup' => $authCheck['via_backup'],
                ]);

                return;
            }

            $balanceService->adjustPending($emp, $type, $fy, -$leaveRequest->total_days);
            $balanceService->mutateBalance($emp, $type, $fy, 'debit', $leaveRequest->total_days, [
                'reference_type' => LeaveRequest::class,
                'reference_id' => $leaveRequest->id,
                'remarks' => "Approved leave #{$leaveRequest->id}",
            ]);

            $leaveRequest->update([
                'approval_status' => 'approved',
                'l2_status' => $leaveRequest->current_level === 2 ? 'approved' : $leaveRequest->l2_status,
                'l2_acted_at' => $leaveRequest->current_level === 2 ? now() : $leaveRequest->l2_acted_at,
                'l1_status' => $leaveRequest->current_level === 1 ? 'approved' : $leaveRequest->l1_status,
                'l1_acted_at' => $leaveRequest->current_level === 1 ? now() : $leaveRequest->l1_acted_at,
                'remarks' => $data['remarks'] ?? $leaveRequest->remarks,
                'acted_via_backup' => $authCheck['via_backup'],
            ]);

            $syncService->syncApprovedLeave($leaveRequest->fresh(['employee', 'leaveType']));

            $notifier->dispatch('leave_status_changed', $emp, [
                'leave_type' => $type->name,
                'start_date' => $leaveRequest->start_date->format('Y-m-d'),
                'end_date' => $leaveRequest->end_date->format('Y-m-d'),
                'status' => 'Approved',
            ]);
        });

        return back()->with(['message' => 'Leave request processed.', 'msg_type' => 'success']);
    }

    public function actFwa(Request $request, FwaRequest $fwaRequest, LeaveAttendanceSyncService $syncService)
    {
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'remarks' => 'nullable|string|max:500',
        ]);

        $fwaRequest->update([
            'approval_status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'acted_by' => auth()->id(),
            'acted_at' => now(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        if ($data['action'] === 'approve') {
            $syncService->syncApprovedFwa($fwaRequest->fresh('employee'));
        }

        return back()->with(['message' => 'FWA request updated.', 'msg_type' => 'success']);
    }
}
