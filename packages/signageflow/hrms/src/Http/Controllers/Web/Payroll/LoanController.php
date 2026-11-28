<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Payroll;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\Loan;
use SignageFlow\Hrms\Models\LoanInstallment;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class LoanController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsPayroll_list')->only(['index']);
        $this->middleware('can:hrmsPayroll_create')->only(['store']);
        $this->middleware('can:hrmsPayroll_edit')->only(['toggleStatus', 'preclose']);
    }

    public function index(Request $request)
    {
        $perPage = (int) ($request->query('perPage') ?? 15);
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($q) => Collection::wrap($value)->each(fn ($v) => $q->orWhere('hrms_loans.loan_number', 'LIKE', "%{$v}%")->orWhere('emp.emp_code', 'LIKE', "%{$v}%")->orWhere('emp.full_name', 'LIKE', "%{$v}%")));
        });

        $query = Loan::query()
            ->leftJoin('hrms_employees as emp', 'hrms_loans.employee_id', '=', 'emp.id')
            ->select(['hrms_loans.*', 'emp.emp_code as emp_code', 'emp.full_name as employee_name']);

        $loans = QueryBuilder::for($query)
            ->defaultSort('-hrms_loans.id')
            ->allowedSorts(['loan_number', AllowedSort::field('employee_name', 'emp.full_name'), 'principal_amount', 'outstanding_amount', 'status'])
            ->allowedFilters([AllowedFilter::exact('status', 'hrms_loans.status'), $global])
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Hrms/Payroll/Loans', [
            'loans' => $loans,
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'emp_code', 'full_name']),
            'resourceNeo' => [
                'resourceName' => 'hrms.payroll.loans',
                'resourceTitle' => 'Loan Master',
                'actions' => ['c', 'r', 'u'],
            ],
        ])->table(function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('loan_number', 'Loan #', searchable: true, sortable: true)
                ->column('emp_code', 'Emp Code', searchable: true, sortable: false)
                ->column('employee_name', 'Employee', searchable: true, sortable: true)
                ->column('loan_type', 'Type', searchable: false, sortable: false)
                ->column('principal_amount', 'Principal', searchable: false, sortable: true)
                ->column('emi_amount', 'EMI', searchable: false, sortable: false)
                ->column('outstanding_amount', 'Outstanding', searchable: false, sortable: true)
                ->column('status', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->selectFilter(key: 'status', label: 'Status', options: ['active' => 'Active', 'paused' => 'Paused', 'closed' => 'Closed'], noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50]);
        });
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'loan_type' => 'required|in:salary_advance,personal_loan,emergency_loan',
            'principal_amount' => 'required|numeric|min:100',
            'tenure_months' => 'required|integer|min:1|max:60',
            'disbursed_date' => 'required|date',
            'start_deduction_date' => 'required|date',
            'remarks' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data) {
            $emi = round((float) $data['principal_amount'] / (int) $data['tenure_months'], 2);
            $loan = Loan::create([
                'loan_number' => 'LN-'.now()->format('Ym').'-'.strtoupper(substr(uniqid(), -4)),
                'employee_id' => $data['employee_id'],
                'loan_type' => $data['loan_type'],
                'principal_amount' => $data['principal_amount'],
                'interest_rate' => 0,
                'tenure_months' => $data['tenure_months'],
                'emi_amount' => $emi,
                'disbursed_date' => $data['disbursed_date'],
                'start_deduction_date' => $data['start_deduction_date'],
                'outstanding_amount' => $data['principal_amount'],
                'status' => 'active',
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $start = Carbon::parse($data['start_deduction_date']);
            for ($i = 0; $i < (int) $data['tenure_months']; $i++) {
                LoanInstallment::create([
                    'loan_id' => $loan->id,
                    'installment_no' => $i + 1,
                    'due_date' => $start->copy()->addMonths($i)->toDateString(),
                    'emi_amount' => $emi,
                    'principal_component' => $emi,
                    'interest_component' => 0,
                    'status' => 'pending',
                ]);
            }
        });

        return back()->with(['message' => 'Loan and EMI schedule created.', 'msg_type' => 'success']);
    }

    public function toggleStatus(Loan $loan)
    {
        if ($loan->status === 'closed') {
            return back()->with(['message' => 'Closed loans cannot be toggled.', 'msg_type' => 'warning']);
        }
        $loan->update(['status' => $loan->status === 'active' ? 'paused' : 'active']);

        return back()->with(['message' => 'Loan status updated.', 'msg_type' => 'success']);
    }

    public function preclose(Loan $loan)
    {
        DB::transaction(function () use ($loan) {
            $loan->installments()->where('status', 'pending')->update(['status' => 'skipped']);
            $loan->update(['outstanding_amount' => 0, 'status' => 'closed']);
        });

        return back()->with(['message' => 'Loan pre-closed.', 'msg_type' => 'success']);
    }
}
