<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Payroll;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\BankTemplate;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\PayGroup;
use SignageFlow\Hrms\Models\SalaryComponent;
use SignageFlow\Hrms\Models\SalaryRevision;
use SignageFlow\Hrms\Models\SalaryStructure;
use SignageFlow\Hrms\Models\SalaryStructureItem;
use SignageFlow\Hrms\Models\StatutoryConfig;
use SignageFlow\Hrms\Services\SalaryFormulaService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class PayrollSetupController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsPayroll_list')->only(['index']);
        $this->middleware('can:hrmsPayroll_create')->only(['storeComponent', 'storePayGroup', 'storeStructure', 'storeRevision', 'importRevisions']);
        $this->middleware('can:hrmsPayroll_edit')->only(['updateComponent', 'updatePayGroup', 'saveStatutory']);
        $this->middleware('can:hrmsPayroll_delete')->only(['destroyComponent', 'destroyPayGroup']);
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'components');
        $perPage = (int) ($request->query('perPage') ?? 15);
        $userClass = config('hrms.models.user');

        [$records, $tableBuilder] = match ($tab) {
            'pay_groups' => $this->queryPayGroups($perPage),
            'statutory' => $this->queryStatutory($perPage),
            'structures' => $this->queryStructures($perPage),
            'revisions' => $this->queryRevisions($perPage),
            default => $this->queryComponents($perPage),
        };

        return Inertia::render('Hrms/Payroll/Setup', [
            'tab' => $tab,
            'records' => $records,
            'users' => $userClass::orderBy('name')->get(['id', 'name']),
            'payGroups' => PayGroup::where('is_active', true)->orderBy('name')->get(['id', 'name', 'pay_frequency']),
            'bankTemplates' => BankTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'emp_code', 'full_name']),
            'components' => SalaryComponent::where('is_active', true)->orderBy('sort_order')->get(),
            'resourceNeo' => [
                'resourceName' => 'hrms.payroll.setup',
                'resourceTitle' => 'Payroll Setup',
                'actions' => ['c', 'r', 'u', 'd'],
            ],
        ])->table($tableBuilder);
    }

    protected function queryComponents(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($q) => Collection::wrap($value)->each(fn ($v) => $q->orWhere('code', 'LIKE', "%{$v}%")->orWhere('name', 'LIKE', "%{$v}%")));
        });

        $records = QueryBuilder::for(SalaryComponent::query())
            ->defaultSort('sort_order')
            ->allowedSorts(['code', 'name', 'type', 'calc_type', 'sort_order'])
            ->allowedFilters(['code', 'name', AllowedFilter::exact('type'), $global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('code', 'Code', searchable: true, sortable: true)
                ->column('name', 'Name', searchable: true, sortable: true)
                ->column('type', 'Type', searchable: false, sortable: true)
                ->column('calc_type', 'Calculation', searchable: false, sortable: true)
                ->column('formula_or_value', 'Value / Formula', searchable: false, sortable: false)
                ->column('is_pf_wage', 'Statutory', searchable: false, sortable: false)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryPayGroups(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($q) => Collection::wrap($value)->each(fn ($v) => $q->orWhere('hrms_pay_groups.code', 'LIKE', "%{$v}%")->orWhere('hrms_pay_groups.name', 'LIKE', "%{$v}%")));
        });

        $query = PayGroup::query()
            ->leftJoin('users as u1', 'hrms_pay_groups.l1_approver_user_id', '=', 'u1.id')
            ->leftJoin('users as u2', 'hrms_pay_groups.l2_approver_user_id', '=', 'u2.id')
            ->select(['hrms_pay_groups.*', 'u1.name as l1_name', 'u2.name as l2_name']);

        $records = QueryBuilder::for($query)
            ->defaultSort('hrms_pay_groups.name')
            ->allowedSorts(['code', 'name', 'pay_frequency'])
            ->allowedFilters([$global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('code', 'Code', searchable: true, sortable: true)
                ->column('name', 'Pay Group', searchable: true, sortable: true)
                ->column('pay_frequency', 'Frequency', searchable: false, sortable: true)
                ->column('l1_name', 'L1 Approver', searchable: false, sortable: false)
                ->column('l2_name', 'L2 Approver', searchable: false, sortable: false)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryStatutory(int $perPage): array
    {
        $records = QueryBuilder::for(StatutoryConfig::query())
            ->defaultSort('-financial_year')
            ->allowedSorts(['financial_year'])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->column('financial_year', 'FY', searchable: false, sortable: true)
                ->column('epf_employee_rate', 'EPF (Emp/Er)', searchable: false, sortable: false)
                ->column('esic_employee_rate', 'ESIC (Emp/Er)', searchable: false, sortable: false)
                ->column('epf_wage_ceiling', 'EPF Ceiling', searchable: false, sortable: false)
                ->column('esic_wage_ceiling', 'ESIC Ceiling', searchable: false, sortable: false)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30]);
        }];
    }

    protected function queryStructures(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($q) => Collection::wrap($value)->each(fn ($v) => $q->orWhere('emp.emp_code', 'LIKE', "%{$v}%")->orWhere('emp.full_name', 'LIKE', "%{$v}%")));
        });

        $query = SalaryStructure::query()
            ->leftJoin('hrms_employees as emp', 'hrms_salary_structures.employee_id', '=', 'emp.id')
            ->leftJoin('hrms_pay_groups as pg', 'hrms_salary_structures.pay_group_id', '=', 'pg.id')
            ->where('hrms_salary_structures.is_active', true)
            ->select(['hrms_salary_structures.*', 'emp.emp_code as emp_code', 'emp.full_name as employee_name', 'pg.name as pay_group_name']);

        $records = QueryBuilder::for($query)
            ->defaultSort('emp.emp_code')
            ->allowedSorts([AllowedSort::field('employee_name', 'emp.full_name'), 'monthly_ctc', 'monthly_gross', 'basic_salary'])
            ->allowedFilters([$global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('emp_code', 'Emp Code', searchable: true, sortable: false)
                ->column('employee_name', 'Employee', searchable: true, sortable: true)
                ->column('pay_group_name', 'Pay Group', searchable: false, sortable: false)
                ->column('basic_salary', 'Basic', searchable: false, sortable: true)
                ->column('monthly_gross', 'Monthly Gross', searchable: false, sortable: true)
                ->column('monthly_ctc', 'Monthly CTC', searchable: false, sortable: true)
                ->column('effective_from', 'Effective', searchable: false, sortable: false)
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryRevisions(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($q) => Collection::wrap($value)->each(fn ($v) => $q->orWhere('emp.emp_code', 'LIKE', "%{$v}%")->orWhere('emp.full_name', 'LIKE', "%{$v}%")));
        });

        $query = SalaryRevision::query()
            ->leftJoin('hrms_employees as emp', 'hrms_salary_revisions.employee_id', '=', 'emp.id')
            ->select(['hrms_salary_revisions.*', 'emp.emp_code as emp_code', 'emp.full_name as employee_name']);

        $records = QueryBuilder::for($query)
            ->defaultSort('-hrms_salary_revisions.effective_from')
            ->allowedSorts(['effective_from', 'new_ctc', 'increment_percent'])
            ->allowedFilters([$global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('emp_code', 'Emp Code', searchable: true, sortable: false)
                ->column('employee_name', 'Employee', searchable: true, sortable: false)
                ->column('previous_ctc', 'Prev CTC', searchable: false, sortable: false)
                ->column('new_ctc', 'New CTC', searchable: false, sortable: true)
                ->column('increment_percent', 'Inc %', searchable: false, sortable: true)
                ->column('effective_from', 'Effective', searchable: false, sortable: true)
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    public function storeComponent(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrms_salary_components,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:earning,deduction,employer_contribution',
            'calc_type' => 'required|in:fixed,percent_of_basic,percent_of_gross,formula',
            'formula_or_value' => 'required|string|max:255',
            'is_pf_wage' => 'boolean',
            'is_esic_wage' => 'boolean',
            'is_pt_wage' => 'boolean',
            'sort_order' => 'integer|min:0|max:999',
        ]);
        SalaryComponent::create($data);

        return back()->with(['message' => 'Salary component created.', 'msg_type' => 'success']);
    }

    public function updateComponent(Request $request, SalaryComponent $component)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrms_salary_components,code,'.$component->id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:earning,deduction,employer_contribution',
            'calc_type' => 'required|in:fixed,percent_of_basic,percent_of_gross,formula',
            'formula_or_value' => 'required|string|max:255',
            'is_pf_wage' => 'boolean',
            'is_esic_wage' => 'boolean',
            'is_pt_wage' => 'boolean',
            'sort_order' => 'integer|min:0|max:999',
        ]);
        $component->update($data);

        return back()->with(['message' => 'Salary component updated.', 'msg_type' => 'success']);
    }

    public function destroyComponent(SalaryComponent $component)
    {
        $component->delete();

        return back()->with(['message' => 'Salary component deleted.', 'msg_type' => 'success']);
    }

    public function storePayGroup(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrms_pay_groups,code',
            'name' => 'required|string|max:255',
            'pay_frequency' => 'required|in:monthly,weekly',
            'l1_approver_user_id' => 'nullable|exists:users,id',
            'l2_approver_user_id' => 'nullable|exists:users,id',
            'default_bank_template_id' => 'nullable|exists:hrms_bank_templates,id',
        ]);
        PayGroup::create($data);

        return back()->with(['message' => 'Pay Group created.', 'msg_type' => 'success']);
    }

    public function updatePayGroup(Request $request, PayGroup $payGroup)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrms_pay_groups,code,'.$payGroup->id,
            'name' => 'required|string|max:255',
            'pay_frequency' => 'required|in:monthly,weekly',
            'l1_approver_user_id' => 'nullable|exists:users,id',
            'l2_approver_user_id' => 'nullable|exists:users,id',
            'default_bank_template_id' => 'nullable|exists:hrms_bank_templates,id',
        ]);
        $payGroup->update($data);

        return back()->with(['message' => 'Pay Group updated.', 'msg_type' => 'success']);
    }

    public function destroyPayGroup(PayGroup $payGroup)
    {
        $payGroup->delete();

        return back()->with(['message' => 'Pay Group deleted.', 'msg_type' => 'success']);
    }

    public function saveStatutory(Request $request)
    {
        $data = $request->validate([
            'financial_year' => 'required|string|max:15',
            'epf_employee_rate' => 'required|numeric|min:0|max:50',
            'epf_employer_rate' => 'required|numeric|min:0|max:50',
            'epf_wage_ceiling' => 'required|numeric|min:0',
            'esic_employee_rate' => 'required|numeric|min:0|max:20',
            'esic_employer_rate' => 'required|numeric|min:0|max:20',
            'esic_wage_ceiling' => 'required|numeric|min:0',
        ]);
        StatutoryConfig::updateOrCreate(['financial_year' => $data['financial_year']], $data);

        return back()->with(['message' => 'Statutory configuration saved.', 'msg_type' => 'success']);
    }

    public function storeStructure(Request $request, SalaryFormulaService $formula)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'pay_group_id' => 'required|exists:hrms_pay_groups,id',
            'effective_from' => 'required|date',
            'monthly_gross' => 'required|numeric|min:0',
            'basic_salary' => 'required|numeric|min:0',
            'monthly_ctc' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($data, $formula) {
            SalaryStructure::where('employee_id', $data['employee_id'])->where('is_active', true)->update(['is_active' => false]);

            $structure = SalaryStructure::create([
                'employee_id' => $data['employee_id'],
                'pay_group_id' => $data['pay_group_id'],
                'effective_from' => $data['effective_from'],
                'monthly_ctc' => $data['monthly_ctc'] ?? $data['monthly_gross'],
                'monthly_gross' => $data['monthly_gross'],
                'basic_salary' => $data['basic_salary'],
                'is_active' => true,
            ]);

            $vars = [
                'BASIC' => (float) $data['basic_salary'],
                'GROSS' => (float) $data['monthly_gross'],
                'CTC' => (float) ($data['monthly_ctc'] ?? $data['monthly_gross']),
            ];

            $components = SalaryComponent::where('is_active', true)->where('type', 'earning')->orderBy('sort_order')->get();
            $allocated = 0.0;
            $specComp = null;

            foreach ($components as $comp) {
                if ($comp->code === 'BASIC') {
                    $amt = $vars['BASIC'];
                } elseif ($comp->code === 'SPEC') {
                    $specComp = $comp;

                    continue;
                } else {
                    $amt = match ($comp->calc_type) {
                        'percent_of_basic' => round($vars['BASIC'] * ((float) $comp->formula_or_value / 100), 2),
                        'percent_of_gross' => round($vars['GROSS'] * ((float) $comp->formula_or_value / 100), 2),
                        'formula' => $formula->evaluate($comp->formula_or_value, $vars),
                        default => (float) $comp->formula_or_value,
                    };
                }
                $allocated += $amt;
                SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $comp->id, 'amount' => $amt]);
            }

            if ($specComp) {
                $balanceAmt = max(0, round($vars['GROSS'] - $allocated, 2));
                SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $specComp->id, 'amount' => $balanceAmt]);
            }
        });

        return back()->with(['message' => 'Salary structure saved.', 'msg_type' => 'success']);
    }

    public function storeRevision(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'new_gross' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'arrear_applicable' => 'boolean',
            'remarks' => 'nullable|string|max:255',
        ]);

        $this->applyRevision((int) $data['employee_id'], (float) $data['new_gross'], $data['effective_from'], (bool) ($data['arrear_applicable'] ?? false), $data['remarks'] ?? null);

        return back()->with(['message' => 'Salary revision applied.', 'msg_type' => 'success']);
    }

    public function importRevisions(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);
        $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, true);
        $count = 0;

        foreach ($rows as $idx => $row) {
            if ($idx === 1 || empty(trim((string) ($row['A'] ?? '')))) {
                continue;
            }
            $emp = Employee::where('emp_code', trim((string) $row['A']))->first();
            $newGross = (float) ($row['B'] ?? 0);
            $eff = ! empty($row['C']) ? date('Y-m-d', strtotime((string) $row['C'])) : now()->startOfMonth()->toDateString();
            $arrear = strtolower(trim((string) ($row['D'] ?? 'no'))) === 'yes';
            if ($emp && $newGross > 0) {
                $this->applyRevision($emp->id, $newGross, $eff, $arrear, 'Bulk Excel Revision');
                $count++;
            }
        }

        return back()->with(['message' => "Applied {$count} bulk salary revisions.", 'msg_type' => 'success']);
    }

    protected function applyRevision(int $employeeId, float $newGross, string $effectiveFrom, bool $arrear, ?string $remarks): void
    {
        DB::transaction(function () use ($employeeId, $newGross, $effectiveFrom, $arrear, $remarks) {
            $current = SalaryStructure::where('employee_id', $employeeId)->where('is_active', true)->first();
            $prevGross = $current?->monthly_gross ?? 0;
            $incPct = $prevGross > 0 ? round((($newGross - $prevGross) / $prevGross) * 100, 2) : 0;

            SalaryRevision::create([
                'employee_id' => $employeeId,
                'previous_ctc' => $prevGross,
                'new_ctc' => $newGross,
                'increment_percent' => $incPct,
                'effective_from' => $effectiveFrom,
                'arrear_applicable' => $arrear,
                'remarks' => $remarks,
                'created_by' => auth()->id(),
            ]);

            if ($current) {
                $ratio = $prevGross > 0 ? ($newGross / $prevGross) : 1;
                $current->update([
                    'monthly_gross' => $newGross,
                    'monthly_ctc' => $newGross,
                    'basic_salary' => round($current->basic_salary * $ratio, 2),
                    'effective_from' => $effectiveFrom,
                ]);
                foreach ($current->items as $item) {
                    $item->update(['amount' => round($item->amount * $ratio, 2)]);
                }
            }
        });
    }
}
