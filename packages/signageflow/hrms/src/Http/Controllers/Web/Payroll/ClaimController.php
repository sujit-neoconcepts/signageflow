<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Payroll;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\Claim;
use SignageFlow\Hrms\Models\Employee;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ClaimController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsClaim_list')->only(['index']);
        $this->middleware('can:hrmsClaim_create')->only(['store']);
        $this->middleware('can:hrmsClaim_approve')->only(['act']);
    }

    public function index(Request $request)
    {
        $perPage = (int) ($request->query('perPage') ?? 15);
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($q) => Collection::wrap($value)->each(fn ($v) => $q
                ->orWhere('hrms_claims.claim_number', 'LIKE', "%{$v}%")
                ->orWhere('emp.emp_code', 'LIKE', "%{$v}%")
                ->orWhere('emp.full_name', 'LIKE', "%{$v}%")
                ->orWhere('hrms_claims.claim_type', 'LIKE', "%{$v}%")));
        });

        $query = Claim::query()
            ->leftJoin('hrms_employees as emp', 'hrms_claims.employee_id', '=', 'emp.id')
            ->select(['hrms_claims.*', 'emp.emp_code as emp_code', 'emp.full_name as employee_name']);

        $claims = QueryBuilder::for($query)
            ->defaultSort('-hrms_claims.id')
            ->allowedSorts(['claim_number', AllowedSort::field('employee_name', 'emp.full_name'), 'claim_date', 'amount', 'approval_status'])
            ->allowedFilters([AllowedFilter::exact('approval_status', 'hrms_claims.approval_status'), $global])
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Hrms/Payroll/Claims', [
            'claims' => $claims,
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'emp_code', 'full_name']),
            'resourceNeo' => [
                'resourceName' => 'hrms.payroll.claims',
                'resourceTitle' => 'E-Claims & Reimbursements',
                'actions' => ['c', 'r', 'u'],
            ],
        ])->table(function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('claim_number', 'Claim #', searchable: true, sortable: true)
                ->column('emp_code', 'Emp Code', searchable: true, sortable: false)
                ->column('employee_name', 'Employee', searchable: true, sortable: true)
                ->column('claim_type', 'Type', searchable: true, sortable: false)
                ->column('claim_date', 'Date', searchable: false, sortable: true)
                ->column('amount', 'Amount', searchable: false, sortable: true)
                ->column('approval_status', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->selectFilter(key: 'approval_status', label: 'Status', options: [
                    'pending_l1' => 'Pending L1',
                    'pending_l2' => 'Pending L2',
                    'approved' => 'Approved',
                    'paid' => 'Paid in PayRun',
                    'rejected' => 'Rejected',
                ], noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50]);
        });
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'claim_type' => 'required|string|max:100',
            'claim_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:1000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'bridge_to_expense' => 'boolean',
        ]);

        DB::transaction(function () use ($request, $data) {
            $emp = Employee::findOrFail($data['employee_id']);
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $disk = config('hrms.storage_disk', 'local');
                $receiptPath = $request->file('receipt')->store('hrms/claims', $disk);
            }

            $hostExpenseId = null;
            $expenseModel = config('hrms.models.expense');
            if (! empty($data['bridge_to_expense']) && $expenseModel && class_exists($expenseModel)) {
                $expense = $expenseModel::create([
                    'exp_date' => $data['claim_date'],
                    'amount' => $data['amount'],
                    'doneby' => $emp->full_name,
                    'exp_cate' => $data['claim_type'],
                    'details' => 'HRMS Claim: '.($data['description'] ?? $data['claim_type']),
                    'amt_type' => 'Expense',
                    'incharge' => auth()->user()?->name ?? 'HRMS',
                ]);
                $hostExpenseId = $expense->id;
            }

            Claim::create([
                'claim_number' => 'CLM-'.now()->format('Ym').'-'.strtoupper(substr(uniqid(), -4)),
                'employee_id' => $emp->id,
                'claim_type' => $data['claim_type'],
                'claim_date' => $data['claim_date'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'receipt_path' => $receiptPath,
                'approval_status' => 'pending_l1',
                'current_approver_id' => $emp->l1_approver_id,
                'host_expense_id' => $hostExpenseId,
            ]);
        });

        if (class_exists(\ActivityLog::class)) {
            \ActivityLog::add('HRMS E-Claim submitted for employee #'.$data['employee_id']);
        }

        return back()->with(['message' => 'E-Claim submitted successfully.', 'msg_type' => 'success']);
    }

    public function act(Request $request, Claim $claim)
    {
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'remarks' => 'nullable|string|max:500',
        ]);

        if (! in_array($claim->approval_status, ['pending_l1', 'pending_l2'], true)) {
            return back()->with(['message' => 'This claim has already been finalized.', 'msg_type' => 'warning']);
        }

        $userId = auth()->id();
        $emp = $claim->employee;

        if ($data['action'] === 'reject') {
            $claim->update([
                'approval_status' => 'rejected',
                'rejection_reason' => $data['remarks'] ?? null,
                'l1_acted_by' => $claim->l1_acted_by ?? $userId,
                'l1_acted_at' => $claim->l1_acted_at ?? now(),
            ]);

            return back()->with(['message' => 'E-Claim rejected.', 'msg_type' => 'success']);
        }

        if ($claim->approval_status === 'pending_l1' && $emp?->l2_approver_id) {
            $claim->update([
                'approval_status' => 'pending_l2',
                'l1_acted_by' => $userId,
                'l1_acted_at' => now(),
                'current_approver_id' => $emp->l2_approver_id,
            ]);

            return back()->with(['message' => 'L1 approved; forwarded to L2 approver.', 'msg_type' => 'success']);
        }

        $claim->update([
            'approval_status' => 'approved',
            'l1_acted_by' => $claim->l1_acted_by ?? $userId,
            'l1_acted_at' => $claim->l1_acted_at ?? now(),
            'l2_acted_by' => $userId,
            'l2_acted_at' => now(),
            'current_approver_id' => null,
        ]);

        if (class_exists(\ActivityLog::class)) {
            \ActivityLog::add('HRMS E-Claim approved: '.$claim->claim_number);
        }

        return back()->with(['message' => 'E-Claim approved for next PayRun payout.', 'msg_type' => 'success']);
    }
}
