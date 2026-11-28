<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Attendance;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SignageFlow\Hrms\Models\AttendancePunch;
use SignageFlow\Hrms\Models\Department;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\Shift;
use SignageFlow\Hrms\Models\WorkLocation;
use SignageFlow\Hrms\Services\AttendanceCalculationService;

class AttendanceMaintenanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hrmsAttendance_edit')->only(['index', 'globalChange', 'backfillMissingDates']);
        $this->middleware('can:hrmsAttendance_import')->only(['importBiometric']);
    }

    public function index()
    {
        return Inertia::render('Hrms/Attendance/BulkMaintenance', [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'workLocations' => WorkLocation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'emp_code', 'full_name']),
        ]);
    }

    public function globalChange(Request $request)
    {
        $data = $request->validate([
            'department_id' => 'nullable|exists:hrms_departments,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:hrms_employees,id',
            'default_shift_id' => 'nullable|exists:hrms_shifts,id',
            'work_location_id' => 'nullable|exists:hrms_work_locations,id',
            'geofence_mode' => 'nullable|in:inherit,strict,flag,bypass',
        ]);

        $query = Employee::active();
        if (! empty($data['employee_ids'])) {
            $query->whereIn('id', $data['employee_ids']);
        } elseif (! empty($data['department_id'])) {
            $query->where('department_id', $data['department_id']);
        }

        $updates = collect($data)->only(['default_shift_id', 'work_location_id', 'geofence_mode'])->filter(fn ($v) => $v !== null)->all();
        $affected = ! empty($updates) ? $query->update($updates) : 0;

        return back()->with(['message' => "Updated {$affected} employee attendance settings.", 'msg_type' => 'success']);
    }

    public function backfillMissingDates(Request $request, AttendanceCalculationService $service)
    {
        $data = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'department_id' => 'nullable|exists:hrms_departments,id',
            'employee_id' => 'nullable|exists:hrms_employees,id',
        ]);

        $employees = Employee::active()
            ->when(! empty($data['employee_id']), fn ($q) => $q->where('id', $data['employee_id']))
            ->when(! empty($data['department_id']), fn ($q) => $q->where('department_id', $data['department_id']))
            ->get();

        $processed = 0;
        foreach (CarbonPeriod::create($data['from_date'], $data['to_date']) as $date) {
            foreach ($employees as $employee) {
                $service->recalculateDay($employee, $date);
                $processed++;
            }
        }

        return back()->with(['message' => "Created/recalculated {$processed} attendance date records.", 'msg_type' => 'success']);
    }

    public function importBiometric(Request $request, AttendanceCalculationService $service)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);
        $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, true);

        $imported = 0;
        $recalcPairs = [];

        DB::transaction(function () use ($rows, &$imported, &$recalcPairs) {
            foreach ($rows as $index => $row) {
                if ($index === 1 || empty(trim((string) ($row['A'] ?? '')))) {
                    continue;
                }

                $empCode = trim((string) $row['A']);
                $emp = Employee::where('emp_code', $empCode)->first();
                if (! $emp || empty($row['B'])) {
                    continue;
                }

                $punchedAt = Carbon::parse((string) $row['B']);
                $direction = in_array(strtolower(trim((string) ($row['C'] ?? 'auto'))), ['in', 'out', 'break_start', 'break_end', 'auto'], true)
                    ? strtolower(trim((string) $row['C']))
                    : 'auto';

                AttendancePunch::create([
                    'employee_id' => $emp->id,
                    'punched_at' => $punchedAt,
                    'punch_date' => $punchedAt->toDateString(),
                    'direction' => $direction,
                    'channel' => 'biometric',
                    'status' => 'valid',
                    'created_by' => auth()->id(),
                ]);

                $recalcPairs[$emp->id.'|'.$punchedAt->toDateString()] = [$emp, $punchedAt->toDateString()];
                $imported++;
            }
        });

        foreach ($recalcPairs as [$emp, $dateStr]) {
            $service->recalculateDay($emp, $dateStr);
        }

        return back()->with(['message' => "Imported {$imported} biometric punches.", 'msg_type' => 'success']);
    }
}
