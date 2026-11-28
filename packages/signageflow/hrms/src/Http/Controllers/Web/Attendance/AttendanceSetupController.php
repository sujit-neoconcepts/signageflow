<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Attendance;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use SignageFlow\Hrms\Models\AttendanceAllowance;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\EmployeeShift;
use SignageFlow\Hrms\Models\KioskDevice;
use SignageFlow\Hrms\Models\Shift;
use SignageFlow\Hrms\Models\WorkLocation;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class AttendanceSetupController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsAttendanceSetup_list')->only(['index']);
        $this->middleware('can:hrmsAttendanceSetup_create')->only(['storeShift', 'storeWorkLocation', 'storeKiosk', 'storeRoster', 'storeAllowance']);
        $this->middleware('can:hrmsAttendanceSetup_edit')->only(['updateShift', 'updateWorkLocation', 'updateKiosk', 'toggleKiosk', 'updateRoster', 'updateAllowance']);
        $this->middleware('can:hrmsAttendanceSetup_delete')->only(['destroyShift', 'destroyWorkLocation', 'destroyKiosk', 'destroyRoster', 'destroyAllowance']);
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'shifts');
        $perPage = (int) ($request->query('perPage') ?? 10);
        $hostLocationClass = config('hrms.models.location');

        [$records, $tableBuilder] = match ($tab) {
            'locations' => $this->queryLocations($perPage),
            'kiosks' => $this->queryKiosks($perPage),
            'rosters' => $this->queryRosters($perPage),
            'allowances' => $this->queryAllowances($perPage),
            default => $this->queryShifts($perPage),
        };

        return Inertia::render('Hrms/Attendance/Setup', [
            'tab' => $tab,
            'records' => $records,
            'hostLocations' => class_exists($hostLocationClass) ? $hostLocationClass::orderBy('name')->get(['id', 'name']) : [],
            'workLocations' => WorkLocation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'emp_code', 'full_name']),
            'resourceNeo' => [
                'resourceName' => 'hrms.attendance.setup',
                'resourceTitle' => 'Attendance Setup',
                'actions' => ['c', 'r', 'u', 'd'],
            ],
        ])->table($tableBuilder);
    }

    protected function queryShifts(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('code', 'LIKE', "%{$v}%")->orWhere('name', 'LIKE', "%{$v}%"));
            });
        });

        $records = QueryBuilder::for(Shift::query())
            ->defaultSort('code')
            ->allowedSorts(['code', 'name', 'start_time', 'is_active'])
            ->allowedFilters(['code', 'name', $global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('code', 'Code', searchable: true, sortable: true)
                ->column('name', 'Shift Name', searchable: true, sortable: true)
                ->column('start_time', 'Timing', searchable: false, sortable: true)
                ->column('grace_in_minutes', 'Grace In/Out', searchable: false, sortable: false)
                ->column('ot_multiplier', 'OT', searchable: false, sortable: false)
                ->column('is_active', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryLocations(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('hrms_work_locations.code', 'LIKE', "%{$v}%")->orWhere('hrms_work_locations.name', 'LIKE', "%{$v}%"));
            });
        });

        $query = WorkLocation::query()
            ->leftJoin('locations', 'hrms_work_locations.host_location_id', '=', 'locations.id')
            ->select(['hrms_work_locations.*', 'locations.name as host_location_name']);

        $records = QueryBuilder::for($query)
            ->defaultSort('hrms_work_locations.name')
            ->allowedSorts(['code', 'name', 'radius_meters', 'geofence_mode', 'is_active'])
            ->allowedFilters(['code', 'name', $global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('code', 'Code', searchable: true, sortable: true)
                ->column('name', 'Location Name', searchable: true, sortable: true)
                ->column('latitude', 'GPS Coordinates', searchable: false, sortable: false)
                ->column('radius_meters', 'Radius (m)', searchable: false, sortable: true)
                ->column('geofence_mode', 'Geofence Mode', searchable: false, sortable: true)
                ->column('is_active', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryKiosks(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('hrms_kiosk_devices.code', 'LIKE', "%{$v}%")->orWhere('hrms_kiosk_devices.name', 'LIKE', "%{$v}%"));
            });
        });

        $query = KioskDevice::query()
            ->leftJoin('hrms_work_locations as wl', 'hrms_kiosk_devices.work_location_id', '=', 'wl.id')
            ->select(['hrms_kiosk_devices.*', 'wl.name as location_name']);

        $records = QueryBuilder::for($query)
            ->defaultSort('hrms_kiosk_devices.name')
            ->allowedSorts(['code', 'name', 'is_active', AllowedSort::field('location_name', 'wl.name')])
            ->allowedFilters(['code', 'name', $global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('code', 'Code', searchable: true, sortable: true)
                ->column('name', 'Kiosk Name', searchable: true, sortable: true)
                ->column('location_name', 'Work Location', searchable: false, sortable: true)
                ->column('is_active', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryRosters(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('emp.emp_code', 'LIKE', "%{$v}%")->orWhere('emp.full_name', 'LIKE', "%{$v}%"));
            });
        });

        $query = EmployeeShift::query()
            ->leftJoin('hrms_employees as emp', 'hrms_employee_shifts.employee_id', '=', 'emp.id')
            ->leftJoin('hrms_shifts as sh', 'hrms_employee_shifts.shift_id', '=', 'sh.id')
            ->select(['hrms_employee_shifts.*', 'emp.emp_code as emp_code', 'emp.full_name as employee_name', 'sh.name as shift_name']);

        $records = QueryBuilder::for($query)
            ->defaultSort('-hrms_employee_shifts.effective_from')
            ->allowedSorts([
                AllowedSort::field('employee_name', 'emp.full_name'),
                AllowedSort::field('shift_name', 'sh.name'),
                'effective_from',
            ])
            ->allowedFilters([$global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('emp_code', 'Emp Code', searchable: true, sortable: false)
                ->column('employee_name', 'Employee', searchable: true, sortable: true)
                ->column('shift_name', 'Shift', searchable: false, sortable: true)
                ->column('effective_from', 'Effective From', searchable: false, sortable: true)
                ->column('effective_to', 'Effective To', searchable: false, sortable: false)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    protected function queryAllowances(int $perPage): array
    {
        $global = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($q) use ($value) {
                Collection::wrap($value)->each(fn ($v) => $q->orWhere('code', 'LIKE', "%{$v}%")->orWhere('name', 'LIKE', "%{$v}%"));
            });
        });

        $records = QueryBuilder::for(AttendanceAllowance::query())
            ->defaultSort('code')
            ->allowedSorts(['code', 'name', 'amount', 'is_active'])
            ->allowedFilters(['code', 'name', $global])
            ->paginate($perPage)
            ->withQueryString();

        return [$records, function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->column('code', 'Code', searchable: true, sortable: true)
                ->column('name', 'Rule Name', searchable: true, sortable: true)
                ->column('amount', 'Amount', searchable: false, sortable: true)
                ->column('min_present_days', 'Criteria', searchable: false, sortable: false)
                ->column('is_active', 'Status', searchable: false, sortable: true)
                ->column(label: 'Actions')
                ->perPageOptions([10, 15, 30, 50, 100]);
        }];
    }

    public function storeShift(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|unique:hrms_shifts,code',
            'name' => 'required|string|max:255',
            'start_time' => 'required',
            'end_time' => 'required',
            'is_overnight' => 'boolean',
            'grace_in_minutes' => 'integer|min:0|max:120',
            'grace_out_minutes' => 'integer|min:0|max:120',
            'break_minutes' => 'integer|min:0|max:240',
            'half_day_minutes' => 'integer|min:60|max:480',
            'full_day_minutes' => 'integer|min:120|max:960',
            'ot_eligible' => 'boolean',
            'ot_min_threshold_minutes' => 'integer|min:0|max:240',
            'ot_multiplier' => 'numeric|min:1|max:4',
            'week_off_days' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        Shift::create($data);

        return back()->with(['message' => 'Shift created.', 'msg_type' => 'success']);
    }

    public function updateShift(Request $request, Shift $shift)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|unique:hrms_shifts,code,'.$shift->id,
            'name' => 'required|string|max:255',
            'start_time' => 'required',
            'end_time' => 'required',
            'is_overnight' => 'boolean',
            'grace_in_minutes' => 'integer|min:0|max:120',
            'grace_out_minutes' => 'integer|min:0|max:120',
            'break_minutes' => 'integer|min:0|max:240',
            'half_day_minutes' => 'integer|min:60|max:480',
            'full_day_minutes' => 'integer|min:120|max:960',
            'ot_eligible' => 'boolean',
            'ot_min_threshold_minutes' => 'integer|min:0|max:240',
            'ot_multiplier' => 'numeric|min:1|max:4',
            'week_off_days' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $shift->update($data);

        return back()->with(['message' => 'Shift updated.', 'msg_type' => 'success']);
    }

    public function destroyShift(Shift $shift)
    {
        $shift->delete();

        return back()->with(['message' => 'Shift deleted.', 'msg_type' => 'success']);
    }

    public function storeWorkLocation(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrms_work_locations,code',
            'name' => 'required|string|max:255',
            'host_location_id' => 'nullable|integer|exists:locations,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'integer|min:10|max:10000',
            'geofence_mode' => 'required|in:strict,flag,disabled',
            'require_selfie' => 'boolean',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        WorkLocation::create($data);

        return back()->with(['message' => 'Work location added.', 'msg_type' => 'success']);
    }

    public function updateWorkLocation(Request $request, WorkLocation $workLocation)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrms_work_locations,code,'.$workLocation->id,
            'name' => 'required|string|max:255',
            'host_location_id' => 'nullable|integer|exists:locations,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'integer|min:10|max:10000',
            'geofence_mode' => 'required|in:strict,flag,disabled',
            'require_selfie' => 'boolean',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $workLocation->update($data);

        return back()->with(['message' => 'Work location updated.', 'msg_type' => 'success']);
    }

    public function destroyWorkLocation(WorkLocation $workLocation)
    {
        $workLocation->delete();

        return back()->with(['message' => 'Work location deleted.', 'msg_type' => 'success']);
    }

    public function storeKiosk(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40|unique:hrms_kiosk_devices,code',
            'name' => 'required|string|max:255',
            'work_location_id' => 'nullable|exists:hrms_work_locations,id',
            'require_snapshot' => 'boolean',
            'allow_pin' => 'boolean',
            'allow_qr' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $plainToken = Str::random(32);
        KioskDevice::create($data + ['device_token_hash' => Hash::make($plainToken)]);

        return back()->with([
            'message' => "Kiosk registered. Save device token now (shown once): {$plainToken}",
            'msg_type' => 'success',
        ]);
    }

    public function updateKiosk(Request $request, KioskDevice $kiosk)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40|unique:hrms_kiosk_devices,code,'.$kiosk->id,
            'name' => 'required|string|max:255',
            'work_location_id' => 'nullable|exists:hrms_work_locations,id',
            'require_snapshot' => 'boolean',
            'allow_pin' => 'boolean',
            'allow_qr' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $kiosk->update($data);

        return back()->with(['message' => 'Kiosk updated.', 'msg_type' => 'success']);
    }

    public function toggleKiosk(KioskDevice $kiosk)
    {
        $kiosk->update(['is_active' => ! $kiosk->is_active]);

        return back()->with(['message' => 'Kiosk status updated.', 'msg_type' => 'success']);
    }

    public function destroyKiosk(KioskDevice $kiosk)
    {
        $kiosk->delete();

        return back()->with(['message' => 'Kiosk deleted.', 'msg_type' => 'success']);
    }

    public function storeRoster(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'shift_id' => 'required|exists:hrms_shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        EmployeeShift::create($data);

        return back()->with(['message' => 'Shift schedule assigned.', 'msg_type' => 'success']);
    }

    public function updateRoster(Request $request, EmployeeShift $roster)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrms_employees,id',
            'shift_id' => 'required|exists:hrms_shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $roster->update($data);

        return back()->with(['message' => 'Shift schedule updated.', 'msg_type' => 'success']);
    }

    public function destroyRoster(EmployeeShift $roster)
    {
        $roster->delete();

        return back()->with(['message' => 'Shift schedule deleted.', 'msg_type' => 'success']);
    }

    public function storeAllowance(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrms_attendance_allowances,code',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'max_absences_allowed' => 'integer|min:0|max:31',
            'max_lates_allowed' => 'integer|min:0|max:31',
            'min_present_days' => 'integer|min:0|max:31',
            'is_active' => 'boolean',
        ]);

        AttendanceAllowance::create($data);

        return back()->with(['message' => 'Attendance allowance rule created.', 'msg_type' => 'success']);
    }

    public function updateAllowance(Request $request, AttendanceAllowance $allowance)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrms_attendance_allowances,code,'.$allowance->id,
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'max_absences_allowed' => 'integer|min:0|max:31',
            'max_lates_allowed' => 'integer|min:0|max:31',
            'min_present_days' => 'integer|min:0|max:31',
            'is_active' => 'boolean',
        ]);

        $allowance->update($data);

        return back()->with(['message' => 'Attendance allowance rule updated.', 'msg_type' => 'success']);
    }

    public function destroyAllowance(AttendanceAllowance $allowance)
    {
        $allowance->delete();

        return back()->with(['message' => 'Attendance allowance rule deleted.', 'msg_type' => 'success']);
    }
}
