<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Core;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\Category;
use SignageFlow\Hrms\Models\Company;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Designation;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\ExtraClassification;
use SignageFlow\Hrms\Models\Section;
use SignageFlow\Hrms\Models\Shift;
use SignageFlow\Hrms\Models\WorkLocation;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class EmployeeController extends Controller
{
    protected array $resourceNeo = [
        'resourceName' => 'hrms.employees',
        'resourceTitle' => 'Employees',
        'iconPath' => 'M4,6H2V20A2,2 0 0,0 4,22H18V20H4V6M20,2A2,2 0 0,1 22,4V16A2,2 0 0,1 20,18H8A2,2 0 0,1 6,16V4A2,2 0 0,1 8,2H20M17,7A3,3 0 0,0 14,4A3,3 0 0,0 11,7A3,3 0 0,0 14,10A3,3 0 0,0 17,7M8,15V16H20V15C20,13 16,11.9 14,11.9C12,11.9 8,13 8,15Z',
        'actions' => ['c', 'r', 'u', 'd'],
    ];

    public function __construct()
    {
        $this->middleware('can:hrmsEmployee_list')->only(['index', 'validation']);
        $this->middleware('can:hrmsEmployee_create')->only(['create', 'store']);
        $this->middleware('can:hrmsEmployee_edit')->only(['edit', 'update']);
        $this->middleware('can:hrmsEmployee_delete')->only(['destroy']);
        $this->middleware('can:hrmsEmployee_bulkUpdate')->only(['bulkUpdate']);
        $this->middleware('can:hrmsEmployee_import')->only(['importView', 'import']);
        $this->middleware('can:hrmsEmployee_export')->only(['export']);
    }

    public function index(Request $request)
    {
        $query = Employee::query()
            ->leftJoin('hrms_departments', 'hrms_employees.department_id', '=', 'hrms_departments.id')
            ->leftJoin('hrms_designations', 'hrms_employees.designation_id', '=', 'hrms_designations.id')
            ->leftJoin('hrms_shifts', 'hrms_employees.default_shift_id', '=', 'hrms_shifts.id')
            ->leftJoin('hrms_work_locations', 'hrms_employees.work_location_id', '=', 'hrms_work_locations.id')
            ->leftJoin('users', 'hrms_employees.user_id', '=', 'users.id')
            ->select([
                'hrms_employees.*',
                'hrms_departments.name as department_name',
                'hrms_designations.name as designation_name',
                'hrms_shifts.code as shift_code',
                'hrms_work_locations.name as location_name',
                'users.email as user_email',
            ]);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                Collection::wrap($value)->each(function ($value) use ($query) {
                    $query->orWhere('hrms_employees.emp_code', 'LIKE', "%{$value}%")
                        ->orWhere('hrms_employees.full_name', 'LIKE', "%{$value}%")
                        ->orWhere('hrms_employees.phone', 'LIKE', "%{$value}%")
                        ->orWhere('hrms_employees.official_email', 'LIKE', "%{$value}%")
                        ->orWhere('hrms_departments.name', 'LIKE', "%{$value}%");
                });
            });
        });

        $perPage = (int) ($request->query('perPage') ?? 10);

        $employees = QueryBuilder::for($query)
            ->defaultSort('hrms_employees.emp_code')
            ->allowedSorts([
                'emp_code',
                'full_name',
                'phone',
                'employment_status',
                AllowedSort::field('department_name', 'hrms_departments.name'),
                AllowedSort::field('designation_name', 'hrms_designations.name'),
                AllowedSort::field('shift_code', 'hrms_shifts.code'),
                AllowedSort::field('location_name', 'hrms_work_locations.name'),
            ])
            ->allowedFilters([
                'emp_code',
                'full_name',
                AllowedFilter::exact('department_id', 'hrms_employees.department_id'),
                AllowedFilter::exact('designation_id', 'hrms_employees.designation_id'),
                AllowedFilter::exact('default_shift_id', 'hrms_employees.default_shift_id'),
                AllowedFilter::exact('work_location_id', 'hrms_employees.work_location_id'),
                AllowedFilter::exact('employment_status', 'hrms_employees.employment_status'),
                $globalSearch,
            ])
            ->paginate($perPage)
            ->withQueryString();

        if (Auth::user()->can('hrmsEmployee_export')) {
            $this->resourceNeo['bulkActions']['csvExport'] = [];
        }

        $departments = Department::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray();
        $designations = Designation::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray();
        $shifts = Shift::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray();
        $locations = WorkLocation::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray();
        $statuses = [
            'active' => 'Active',
            'probation' => 'Probation',
            'notice' => 'Notice',
            'resigned' => 'Resigned',
            'terminated' => 'Terminated',
        ];

        return Inertia::render('Hrms/Core/EmployeesIndex', [
            'employees' => $employees,
            'resourceNeo' => $this->resourceNeo,
            'orgOptions' => $this->orgOptions(),
        ])->table(function (InertiaTable $table) use ($departments, $designations, $shifts, $locations, $statuses) {
            $table->withGlobalSearch()
                ->column('emp_code', 'Emp Code', searchable: true, sortable: true)
                ->column('full_name', 'Name', searchable: true, sortable: true)
                ->column('department_name', 'Department', searchable: false, sortable: true)
                ->column('designation_name', 'Designation', searchable: false, sortable: true)
                ->column('shift_code', 'Shift', searchable: false, sortable: true)
                ->column('location_name', 'Location', searchable: false, sortable: true)
                ->column('user_email', 'User Login', searchable: false, sortable: false)
                ->column('employment_status', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->selectFilter(key: 'department_id', label: 'Department', options: $departments, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'designation_id', label: 'Designation', options: $designations, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'default_shift_id', label: 'Shift', options: $shifts, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'work_location_id', label: 'Location', options: $locations, noFilterOptionLabel: 'All')
                ->selectFilter(key: 'employment_status', label: 'Status', options: $statuses, noFilterOptionLabel: 'All')
                ->perPageOptions([10, 15, 30, 50, 100]);
        });
    }

    public function create()
    {
        return Inertia::render('Hrms/Core/EmployeeForm', [
            'employee' => null,
            'orgOptions' => $this->orgOptions(),
            'hostUsers' => $this->availableHostUsers(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEmployee($request);
        $data['full_name'] = trim($data['first_name'].' '.($data['last_name'] ?? ''));
        $data['qr_badge_token'] = Str::random(32);

        if ($request->filled('kiosk_pin')) {
            $data['kiosk_pin_hash'] = Hash::make($request->input('kiosk_pin'));
        }

        $employee = DB::transaction(function () use ($data, $request) {
            $emp = Employee::create($data);
            if ($request->filled('assigned_location_ids')) {
                $emp->assignedWorkLocations()->sync($request->input('assigned_location_ids', []));
            }

            return $emp;
        });

        if (class_exists(\ActivityLog::class)) {
            \ActivityLog::add(['action' => 'added', 'module' => 'hrms_employee', 'data_key' => $employee->emp_code]);
        }

        return redirect()->route('hrms.employees.index')->with(['message' => 'Employee created successfully.', 'msg_type' => 'success']);
    }

    public function edit(Employee $employee)
    {
        $employee->load('assignedWorkLocations:id,name');

        return Inertia::render('Hrms/Core/EmployeeForm', [
            'employee' => $employee,
            'orgOptions' => $this->orgOptions(),
            'hostUsers' => $this->availableHostUsers($employee->user_id),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validateEmployee($request, $employee->id);
        $data['full_name'] = trim($data['first_name'].' '.($data['last_name'] ?? ''));

        if ($request->filled('kiosk_pin')) {
            $data['kiosk_pin_hash'] = Hash::make($request->input('kiosk_pin'));
        }

        DB::transaction(function () use ($employee, $data, $request) {
            $employee->update($data);
            if ($request->has('assigned_location_ids')) {
                $employee->assignedWorkLocations()->sync($request->input('assigned_location_ids', []));
            }
        });

        if (class_exists(\ActivityLog::class)) {
            \ActivityLog::add(['action' => 'updated', 'module' => 'hrms_employee', 'data_key' => $employee->emp_code]);
        }

        return redirect()->route('hrms.employees.index')->with(['message' => 'Employee updated successfully.', 'msg_type' => 'success']);
    }

    public function destroy(Employee $employee)
    {
        $code = $employee->emp_code;
        $employee->delete();

        if (class_exists(\ActivityLog::class)) {
            \ActivityLog::add(['action' => 'deleted', 'module' => 'hrms_employee', 'data_key' => $code]);
        }

        return back()->with(['message' => 'Employee removed.', 'msg_type' => 'success']);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:hrms_employees,id',
            'department_id' => 'nullable|exists:hrms_departments,id',
            'default_shift_id' => 'nullable|exists:hrms_shifts,id',
            'work_location_id' => 'nullable|exists:hrms_work_locations,id',
            'l1_approver_id' => 'nullable|exists:hrms_employees,id',
            'employment_status' => 'nullable|in:active,probation,notice,resigned,terminated',
        ]);

        $updates = collect($validated)->except('employee_ids')->filter(fn ($v) => $v !== null)->all();
        if (! empty($updates)) {
            Employee::whereIn('id', $validated['employee_ids'])->update($updates);
        }

        return back()->with(['message' => 'Bulk update applied to '.count($validated['employee_ids']).' employees.', 'msg_type' => 'success']);
    }

    public function validation()
    {
        $issues = Employee::active()->get()->map(function (Employee $emp) {
            $warnings = [];
            if (! $emp->default_shift_id) {
                $warnings[] = 'Missing Default Shift';
            }
            if (! $emp->work_location_id) {
                $warnings[] = 'Missing Work Location';
            }
            if (! $emp->l1_approver_id) {
                $warnings[] = 'Missing L1 Approver';
            }
            if (! $emp->bank_account_no || ! $emp->bank_ifsc) {
                $warnings[] = 'Incomplete Bank Details';
            }
            if ($emp->epf_enabled && ! $emp->uan_number) {
                $warnings[] = 'Missing UAN for EPF';
            }

            return [
                'id' => $emp->id,
                'emp_code' => $emp->emp_code,
                'full_name' => $emp->full_name,
                'warnings' => $warnings,
            ];
        })->filter(fn ($row) => count($row['warnings']) > 0)->values();

        return Inertia::render('Hrms/Core/EmployeeValidation', ['issues' => $issues]);
    }

    public function importView()
    {
        return Inertia::render('Hrms/Core/EmployeeImport');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($rows, &$created, &$updated, &$errors) {
            foreach ($rows as $index => $row) {
                if ($index === 1 || empty(trim((string) ($row['A'] ?? '')))) {
                    continue;
                }
                $empCode = trim((string) $row['A']);
                $firstName = trim((string) ($row['B'] ?? ''));
                if (! $firstName) {
                    $errors[] = "Row {$index}: Missing first name for {$empCode}";

                    continue;
                }

                $deptId = ! empty($row['E']) ? Department::where('code', trim((string) $row['E']))->value('id') : null;
                $desigId = ! empty($row['F']) ? Designation::where('code', trim((string) $row['F']))->value('id') : null;
                $shiftId = ! empty($row['G']) ? Shift::where('code', trim((string) $row['G']))->value('id') : null;

                $payload = [
                    'first_name' => $firstName,
                    'last_name' => trim((string) ($row['C'] ?? '')) ?: null,
                    'full_name' => trim($firstName.' '.trim((string) ($row['C'] ?? ''))),
                    'date_of_joining' => ! empty($row['D']) ? date('Y-m-d', strtotime((string) $row['D'])) : now()->toDateString(),
                    'department_id' => $deptId,
                    'designation_id' => $desigId,
                    'default_shift_id' => $shiftId,
                    'official_email' => trim((string) ($row['H'] ?? '')) ?: null,
                    'phone' => trim((string) ($row['I'] ?? '')) ?: null,
                    'gender' => in_array(strtolower(trim((string) ($row['J'] ?? ''))), ['male', 'female', 'other'], true) ? strtolower(trim((string) $row['J'])) : null,
                ];

                $existing = Employee::where('emp_code', $empCode)->first();
                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    Employee::create(array_merge($payload, [
                        'emp_code' => $empCode,
                        'qr_badge_token' => Str::random(32),
                    ]));
                    $created++;
                }
            }
        });

        return back()->with([
            'message' => "Import completed: {$created} created, {$updated} updated.".($errors ? ' Errors: '.implode('; ', $errors) : ''),
            'msg_type' => $errors ? 'warning' : 'success',
        ]);
    }

    public function export(Request $request)
    {
        $employees = Employee::with(['department', 'designation', 'defaultShift', 'workLocation'])
            ->filterByOrg($request->all())
            ->orderBy('emp_code')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Emp Code', 'Full Name', 'Status', 'Department', 'Designation', 'Shift', 'Work Location', 'Email', 'Phone', 'DOJ'], null, 'A1');

        $rowNum = 2;
        foreach ($employees as $emp) {
            $sheet->fromArray([
                $emp->emp_code,
                $emp->full_name,
                $emp->employment_status,
                $emp->department?->name,
                $emp->designation?->name,
                $emp->defaultShift?->name,
                $emp->workLocation?->name,
                $emp->official_email,
                $emp->phone,
                $emp->date_of_joining?->format('Y-m-d'),
            ], null, 'A'.$rowNum++);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'hrms-employees-'.now()->format('Ymd').'.xlsx');
    }

    protected function orgOptions(): array
    {
        return [
            'companies' => Company::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name', 'company_id']),
            'sections' => Section::where('is_active', true)->orderBy('name')->get(['id', 'name', 'department_id']),
            'designations' => Designation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'extraClassifications' => ExtraClassification::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'workLocations' => WorkLocation::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'approvers' => Employee::active()->orderBy('full_name')->get(['id', 'emp_code', 'full_name']),
        ];
    }

    protected function availableHostUsers(?int $currentUserId = null)
    {
        $userClass = config('hrms.models.user');
        $linkedIds = Employee::whereNotNull('user_id')
            ->when($currentUserId, fn ($q) => $q->where('user_id', '!=', $currentUserId))
            ->pluck('user_id');

        return $userClass::whereNotIn('id', $linkedIds)->orderBy('name')->get(['id', 'name', 'email']);
    }

    protected function validateEmployee(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'emp_code' => 'required|string|max:40|unique:hrms_employees,emp_code'.($ignoreId ? ','.$ignoreId : ''),
            'user_id' => 'nullable|integer|exists:users,id|unique:hrms_employees,user_id'.($ignoreId ? ','.$ignoreId : ''),
            'first_name' => 'required|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'date_of_joining' => 'required|date',
            'date_of_exit' => 'nullable|date',
            'employment_status' => 'required|in:active,probation,notice,resigned,terminated',
            'employment_type' => 'required|in:permanent,contract,intern,daily_wage',
            'company_id' => 'nullable|exists:hrms_companies,id',
            'department_id' => 'nullable|exists:hrms_departments,id',
            'section_id' => 'nullable|exists:hrms_sections,id',
            'designation_id' => 'nullable|exists:hrms_designations,id',
            'category_id' => 'nullable|exists:hrms_categories,id',
            'extra_classification_id' => 'nullable|exists:hrms_extra_classifications,id',
            'l1_approver_id' => 'nullable|exists:hrms_employees,id',
            'l2_approver_id' => 'nullable|exists:hrms_employees,id',
            'backup_approver_id' => 'nullable|exists:hrms_employees,id',
            'default_shift_id' => 'nullable|exists:hrms_shifts,id',
            'work_location_id' => 'nullable|exists:hrms_work_locations,id',
            'geofence_mode' => 'required|in:inherit,strict,flag,bypass',
            'selfie_required' => 'nullable|boolean',
            'mobile_attendance_enabled' => 'boolean',
            'official_email' => 'nullable|email|max:255',
            'personal_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'emergency_contact' => 'nullable|string|max:60',
            'pan_number' => 'nullable|string|max:20',
            'aadhaar_last4' => 'nullable|string|max:4',
            'uan_number' => 'nullable|string|max:30',
            'esic_number' => 'nullable|string|max:30',
            'bank_name' => 'nullable|string|max:120',
            'bank_account_no' => 'nullable|string|max:40',
            'bank_ifsc' => 'nullable|string|max:20',
            'pt_state_code' => 'nullable|string|max:10',
            'epf_enabled' => 'boolean',
            'esic_enabled' => 'boolean',
            'pt_enabled' => 'boolean',
            'lwf_enabled' => 'boolean',
            'tax_regime' => 'in:new,old',
        ]);
    }
}
