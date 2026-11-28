<?php

namespace SignageFlow\Hrms\Http\Controllers\Mobile;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use SignageFlow\Hrms\Models\AttendanceDay;
use SignageFlow\Hrms\Models\AttendancePunch;
use SignageFlow\Hrms\Models\AttendanceRequest;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\WorkLocation;
use SignageFlow\Hrms\Services\ApprovalRoutingService;
use SignageFlow\Hrms\Services\AttendanceCalculationService;
use SignageFlow\Hrms\Services\GeofenceService;

class AttendanceMobileController extends Controller
{
    public function today(Request $request, AttendanceCalculationService $calculation)
    {
        $employee = $this->resolveEmployee($request);
        $today = now();
        $shift = $calculation->resolveShiftForDate($employee, $today);

        $day = AttendanceDay::where('employee_id', $employee->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->first();

        $punches = AttendancePunch::where('employee_id', $employee->id)
            ->whereDate('punch_date', $today->toDateString())
            ->orderBy('punched_at')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'direction' => $p->direction,
                'punched_at' => $p->punched_at->format('H:i:s'),
                'channel' => $p->channel,
                'is_out_of_geofence' => $p->is_out_of_geofence,
                'distance_meters' => $p->distance_meters,
            ]);

        $locations = collect();
        if ($employee->workLocation) {
            $locations->push($employee->workLocation);
        }
        $locations = $locations->merge($employee->assignedWorkLocations()->where('is_active', true)->get())->unique('id')->values();

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'emp_code' => $employee->emp_code,
                'full_name' => $employee->full_name,
                'geofence_mode' => $employee->geofence_mode,
                'selfie_required' => $employee->selfie_required ?? $employee->workLocation?->require_selfie ?? false,
                'mobile_attendance_enabled' => $employee->mobile_attendance_enabled,
            ],
            'shift' => $shift ? [
                'code' => $shift->code,
                'name' => $shift->name,
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time' => substr($shift->end_time, 0, 5),
            ] : null,
            'work_locations' => $locations->map(fn (WorkLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'latitude' => $l->latitude,
                'longitude' => $l->longitude,
                'radius_meters' => $l->radius_meters,
                'geofence_mode' => $l->geofence_mode,
                'require_selfie' => $l->require_selfie,
            ]),
            'today_summary' => [
                'date' => $today->toDateString(),
                'status' => $day?->status ?? 'not_punched',
                'first_in' => $day?->first_in_at?->format('H:i'),
                'last_out' => $day?->last_out_at?->format('H:i'),
                'worked_minutes' => $day?->worked_minutes ?? 0,
            ],
            'punches' => $punches,
        ]);
    }

    public function punch(Request $request, GeofenceService $geofence, AttendanceCalculationService $calculation)
    {
        $employee = $this->resolveEmployee($request);

        if (! $employee->mobile_attendance_enabled) {
            return response()->json(['message' => 'Mobile attendance is disabled for your profile.'], 403);
        }

        $data = $request->validate([
            'direction' => 'required|in:in,out,break_start,break_end',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'work_location_id' => 'nullable|exists:hrms_work_locations,id',
            'selfie' => 'nullable|image|max:5120',
            'device_meta' => 'nullable|array',
        ]);

        $preferred = ! empty($data['work_location_id']) ? WorkLocation::find($data['work_location_id']) : null;
        $eval = $geofence->evaluate($employee, (float) $data['latitude'], (float) $data['longitude'], $preferred);

        if ($eval['action'] === 'block') {
            return response()->json([
                'message' => "You are outside the allowed geofence ({$eval['distance_meters']}m from {$eval['location']?->name}).",
                'distance_meters' => $eval['distance_meters'],
            ], 422);
        }

        if ($eval['selfie_required'] && ! $request->hasFile('selfie')) {
            return response()->json(['message' => 'A live selfie is required for mobile attendance.'], 422);
        }

        $selfiePath = $request->hasFile('selfie')
            ? $request->file('selfie')->store('hrms/selfies/'.$employee->id, config('hrms.storage_disk', 'local'))
            : null;

        $now = now();
        $punch = DB::transaction(function () use ($employee, $data, $eval, $selfiePath, $now, $request) {
            return AttendancePunch::create([
                'employee_id' => $employee->id,
                'punched_at' => $now,
                'punch_date' => $now->toDateString(),
                'direction' => $data['direction'],
                'channel' => 'mobile',
                'work_location_id' => $eval['location']?->id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'distance_meters' => $eval['distance_meters'],
                'is_out_of_geofence' => $eval['is_out_of_geofence'],
                'selfie_path' => $selfiePath,
                'device_meta' => $data['device_meta'] ?? ['ip' => $request->ip()],
                'status' => $eval['action'] === 'flag' ? 'flagged' : 'valid',
                'created_by' => $request->user()->id,
            ]);
        });

        $day = $calculation->recalculateDay($employee, $now);

        return response()->json([
            'message' => $eval['is_out_of_geofence']
                ? 'Punch recorded (flagged as outside geofence).'
                : 'Punch recorded successfully.',
            'punch' => [
                'id' => $punch->id,
                'direction' => $punch->direction,
                'punched_at' => $punch->punched_at->format('H:i:s'),
                'is_out_of_geofence' => $punch->is_out_of_geofence,
                'distance_meters' => $punch->distance_meters,
            ],
            'day_status' => $day->status,
        ], 201);
    }

    public function history(Request $request)
    {
        $employee = $this->resolveEmployee($request);
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $days = AttendanceDay::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('attendance_date')
            ->get()
            ->map(fn ($d) => [
                'date' => $d->attendance_date->format('Y-m-d'),
                'status' => $d->status,
                'leave_code' => $d->leave_code,
                'first_in' => $d->first_in_at?->format('H:i'),
                'last_out' => $d->last_out_at?->format('H:i'),
                'worked_minutes' => $d->worked_minutes,
                'late_minutes' => $d->late_minutes,
                'early_out_minutes' => $d->early_out_minutes,
                'overtime_minutes' => $d->overtime_minutes,
                'is_geofence_flagged' => $d->is_geofence_flagged,
            ]);

        return response()->json(['month' => $month, 'year' => $year, 'days' => $days]);
    }

    public function requests(Request $request)
    {
        $employee = $this->resolveEmployee($request);
        $rows = AttendanceRequest::where('employee_id', $employee->id)
            ->latest()
            ->take(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'attendance_date' => $r->attendance_date->format('Y-m-d'),
                'request_type' => $r->request_type,
                'requested_in' => $r->requested_in_at?->format('H:i'),
                'requested_out' => $r->requested_out_at?->format('H:i'),
                'reason' => $r->reason,
                'approval_status' => $r->approval_status,
            ]);

        return response()->json(['requests' => $rows]);
    }

    public function regularize(Request $request, ApprovalRoutingService $routing)
    {
        $employee = $this->resolveEmployee($request);

        $data = $request->validate([
            'attendance_date' => 'required|date',
            'request_type' => 'required|in:missed_in,missed_out,time_correction,geofence_override',
            'requested_in_time' => 'nullable|date_format:H:i',
            'requested_out_time' => 'nullable|date_format:H:i',
            'reason' => 'required|string|max:500',
        ]);

        $dateStr = Carbon::parse($data['attendance_date'])->toDateString();
        $l1 = $routing->resolveApprover($employee, 1, 'attendance');

        $req = AttendanceRequest::create([
            'employee_id' => $employee->id,
            'attendance_date' => $dateStr,
            'request_type' => $data['request_type'],
            'requested_in_at' => ! empty($data['requested_in_time']) ? Carbon::parse($dateStr.' '.$data['requested_in_time']) : null,
            'requested_out_at' => ! empty($data['requested_out_time']) ? Carbon::parse($dateStr.' '.$data['requested_out_time']) : null,
            'reason' => $data['reason'],
            'approval_status' => 'pending_l1',
            'current_level' => 1,
            'current_approver_id' => $l1['approver']?->id,
        ]);

        return response()->json([
            'message' => 'Regularization request submitted.',
            'request_id' => $req->id,
        ], 201);
    }

    protected function resolveEmployee(Request $request): Employee
    {
        $employee = Employee::active()->where('user_id', $request->user()->id)->with(['workLocation', 'defaultShift'])->first();
        if (! $employee) {
            abort(403, 'No active HRMS employee profile linked to this user.');
        }

        return $employee;
    }
}
