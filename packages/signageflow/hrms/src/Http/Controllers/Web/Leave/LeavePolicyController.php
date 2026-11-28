<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Leave;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\Company;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\FwaPolicy;
use SignageFlow\Hrms\Models\Holiday;
use SignageFlow\Hrms\Models\LeaveType;
use SignageFlow\Hrms\Models\WorkLocation;
use SignageFlow\Hrms\Services\FinancialYearHelper;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LeavePolicyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsLeaveSetup_list')->only(['index']);
        $this->middleware('can:hrmsLeaveSetup_create')->only(['storeLeaveType', 'storeFwaPolicy', 'storeHoliday']);
        $this->middleware('can:hrmsLeaveSetup_edit')->only(['updateLeaveType', 'updateFwaPolicy', 'updateHoliday']);
        $this->middleware('can:hrmsLeaveSetup_delete')->only(['destroyLeaveType', 'destroyFwaPolicy', 'destroyHoliday']);
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'leave_types');
        $perPage = (int) ($request->query('perPage') ?? 10);

        [$records, $tableBuilder] = match ($tab) {
            'fwa_policies' => $this->queryFwaPolicies($perPage),
            'calendar_controls' => $this->queryHolidays($perPage),
            default => $this->queryLeaveTypes($perPage),
        };

        return Inertia::render('Hrms/Leave/Setup', [
            'tab' => $tab,
            'currentFy' => FinancialYearHelper::current(),
            'records' => $records,
            'companies' => Company::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'workLocations' => WorkLocation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'resourceNeo' => [
                'resourceName' => 'hrms.leave.setup',
                'resourceTitle' => 'Leave & Calendar Setup',
                'actions' => ['c', 'r', 'u', 'd'],
            ],
        ])->table($tableBuilder);
    }

    protected function queryLeaveTypes(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('code', 'LIKE', "%{$v}%")->orWhere('name', 'LIKE', "%{$v}%"));
            });
        });

        $records = QueryBuilder::for(LeaveType::query())
            ->defaultSort('code')
            ->allowedSorts(['code', 'name', 'accrual_frequency', 'annual_quota', 'is_paid', 'is_active'])
            ->allowedFilters(['code', 'name', AllowedFilter::exact('accrual_frequency'), $global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('code', 'Code', searchable: true, sortable: true)
                ->column('name', 'Name', searchable: true, sortable: true)
                ->column('accrual_frequency', 'Accrual', searchable: false, sortable: true)
                ->column('annual_quota', 'Quota / CF', searchable: false, sortable: true)
                ->column('is_paid', 'Paid', searchable: false, sortable: true)
                ->column('is_active', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryFwaPolicies(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('code', 'LIKE', "%{$v}%")->orWhere('name', 'LIKE', "%{$v}%"));
            });
        });

        $records = QueryBuilder::for(FwaPolicy::query())
            ->defaultSort('code')
            ->allowedSorts(['code', 'name', 'mode', 'max_days_per_month', 'is_active'])
            ->allowedFilters(['code', 'name', $global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('code', 'Code', searchable: true, sortable: true)
                ->column('name', 'Policy Name', searchable: true, sortable: true)
                ->column('mode', 'Mode', searchable: false, sortable: true)
                ->column('max_days_per_month', 'Max Days/Mo', searchable: false, sortable: true)
                ->column('is_active', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryHolidays(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('name', 'LIKE', "%{$v}%")->orWhere('financial_year', 'LIKE', "%{$v}%"));
            });
        });

        $records = QueryBuilder::for(Holiday::query())
            ->defaultSort('start_date')
            ->allowedSorts(['financial_year', 'name', 'holiday_type', 'start_date', 'is_active'])
            ->allowedFilters([
                'financial_year',
                AllowedFilter::exact('holiday_type'),
                $global,
            ])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('financial_year', 'FY', searchable: true, sortable: true)
                ->column('name', 'Label', searchable: true, sortable: true)
                ->column('holiday_type', 'Type', searchable: false, sortable: true)
                ->column('start_date', 'Start Date', searchable: false, sortable: true)
                ->column('end_date', 'End Date', searchable: false, sortable: false)
                ->column(label: 'Actions')
                ->selectFilter(key: 'holiday_type', label: 'Type', options: [
                    'public_holiday' => 'Public Holiday',
                    'restricted_day' => 'Restricted Day',
                    'blocked_annual_leave' => 'Blocked Annual Leave',
                    'rest_day' => 'Rest Day',
                ], noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    public function storeLeaveType(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|unique:hrms_leave_types,code',
            'name' => 'required|string|max:255',
            'is_paid' => 'boolean',
            'allow_half_day' => 'boolean',
            'requires_attachment' => 'boolean',
            'attachment_after_days' => 'integer|min:0|max:30',
            'accrual_frequency' => 'required|in:monthly,quarterly,annual,none',
            'annual_quota' => 'required|numeric|min:0|max:365',
            'max_carry_forward' => 'required|numeric|min:0|max:365',
            'allow_encashment' => 'boolean',
            'gender_restriction' => 'required|in:all,female,male',
            'min_tenure_days' => 'integer|min:0|max:3650',
            'is_statutory' => 'boolean',
            'is_active' => 'boolean',
        ]);

        LeaveType::create($data);

        return back()->with(['message' => 'Leave type created.', 'msg_type' => 'success']);
    }

    public function updateLeaveType(Request $request, LeaveType $leaveType)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|unique:hrms_leave_types,code,'.$leaveType->id,
            'name' => 'required|string|max:255',
            'is_paid' => 'boolean',
            'allow_half_day' => 'boolean',
            'requires_attachment' => 'boolean',
            'attachment_after_days' => 'integer|min:0|max:30',
            'accrual_frequency' => 'required|in:monthly,quarterly,annual,none',
            'annual_quota' => 'required|numeric|min:0|max:365',
            'max_carry_forward' => 'required|numeric|min:0|max:365',
            'allow_encashment' => 'boolean',
            'gender_restriction' => 'required|in:all,female,male',
            'min_tenure_days' => 'integer|min:0|max:3650',
            'is_active' => 'boolean',
        ]);

        $leaveType->update($data);

        return back()->with(['message' => 'Leave type updated.', 'msg_type' => 'success']);
    }

    public function destroyLeaveType(LeaveType $leaveType)
    {
        $leaveType->delete();

        return back()->with(['message' => 'Leave type deleted.', 'msg_type' => 'success']);
    }

    public function storeFwaPolicy(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrms_fwa_policies,code',
            'name' => 'required|string|max:255',
            'mode' => 'required|in:wfh,flexi_hours,remote_site',
            'max_days_per_month' => 'integer|min:1|max:31',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
        ]);

        FwaPolicy::create($data);

        return back()->with(['message' => 'FWA policy saved.', 'msg_type' => 'success']);
    }

    public function updateFwaPolicy(Request $request, FwaPolicy $fwaPolicy)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrms_fwa_policies,code,'.$fwaPolicy->id,
            'name' => 'required|string|max:255',
            'mode' => 'required|in:wfh,flexi_hours,remote_site',
            'max_days_per_month' => 'integer|min:1|max:31',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $fwaPolicy->update($data);

        return back()->with(['message' => 'FWA policy updated.', 'msg_type' => 'success']);
    }

    public function destroyFwaPolicy(FwaPolicy $fwaPolicy)
    {
        $fwaPolicy->delete();

        return back()->with(['message' => 'FWA policy deleted.', 'msg_type' => 'success']);
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'holiday_type' => 'required|in:public_holiday,restricted_day,blocked_annual_leave,rest_day',
            'company_id' => 'nullable|exists:hrms_companies,id',
            'department_id' => 'nullable|exists:hrms_departments,id',
            'work_location_id' => 'nullable|exists:hrms_work_locations,id',
        ]);

        $data['financial_year'] = FinancialYearHelper::forDate($data['start_date']);
        Holiday::create($data);

        return back()->with(['message' => 'Calendar control entry added.', 'msg_type' => 'success']);
    }

    public function updateHoliday(Request $request, Holiday $holiday)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'holiday_type' => 'required|in:public_holiday,restricted_day,blocked_annual_leave,rest_day',
            'company_id' => 'nullable|exists:hrms_companies,id',
            'department_id' => 'nullable|exists:hrms_departments,id',
            'work_location_id' => 'nullable|exists:hrms_work_locations,id',
        ]);

        $data['financial_year'] = FinancialYearHelper::forDate($data['start_date']);
        $holiday->update($data);

        return back()->with(['message' => 'Calendar control entry updated.', 'msg_type' => 'success']);
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with(['message' => 'Calendar entry removed.', 'msg_type' => 'success']);
    }
}
