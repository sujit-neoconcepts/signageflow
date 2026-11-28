<?php

namespace SignageFlow\Hrms\Http\Controllers\Web\Attendance;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use SignageFlow\Hrms\Models\AttendancePunch;
use SignageFlow\Hrms\Models\Employee;
use SignageFlow\Hrms\Models\KioskDevice;
use SignageFlow\Hrms\Services\AttendanceCalculationService;

class KioskTerminalController extends Controller
{
    public function show(Request $request)
    {
        return Inertia::render('Hrms/Attendance/KioskTerminal', [
            'kioskCode' => $request->query('code'),
        ]);
    }

    public function punch(Request $request, AttendanceCalculationService $calculation)
    {
        $data = $request->validate([
            'kiosk_code' => 'required|string',
            'device_token' => 'required|string',
            'emp_code' => 'nullable|string',
            'pin' => 'nullable|string',
            'qr_token' => 'nullable|string',
            'direction' => 'nullable|in:in,out,auto',
        ]);

        $kiosk = KioskDevice::where('code', $data['kiosk_code'])->where('is_active', true)->first();
        if (! $kiosk || ! Hash::check($data['device_token'], $kiosk->device_token_hash)) {
            return response()->json(['message' => 'Invalid or inactive kiosk device credentials.'], 403);
        }

        $kiosk->update(['last_seen_at' => now()]);

        if (! empty($data['qr_token']) && $kiosk->allow_qr) {
            $employee = Employee::active()->where('qr_badge_token', $data['qr_token'])->first();
        } else {
            $employee = Employee::active()->where('emp_code', $data['emp_code'] ?? '')->first();
            if ($employee && $kiosk->allow_pin && $employee->kiosk_pin_hash && ! Hash::check((string) ($data['pin'] ?? ''), $employee->kiosk_pin_hash)) {
                return response()->json(['message' => 'Invalid Employee PIN.'], 422);
            }
        }

        if (! $employee) {
            return response()->json(['message' => 'Employee not found or inactive.'], 404);
        }

        $now = now();
        $punch = AttendancePunch::create([
            'employee_id' => $employee->id,
            'punched_at' => $now,
            'punch_date' => $now->toDateString(),
            'direction' => $data['direction'] ?? 'auto',
            'channel' => 'kiosk',
            'work_location_id' => $kiosk->work_location_id,
            'kiosk_device_id' => $kiosk->id,
            'status' => 'valid',
        ]);

        $day = $calculation->recalculateDay($employee, $now);

        return response()->json([
            'message' => "Punch recorded for {$employee->full_name} at {$now->format('H:i:s')}",
            'employee' => ['emp_code' => $employee->emp_code, 'full_name' => $employee->full_name],
            'punch_id' => $punch->id,
            'status' => $day->status,
        ]);
    }

    public function biometricWebhook(Request $request, AttendanceCalculationService $calculation)
    {
        $data = $request->validate([
            'kiosk_code' => 'required|string',
            'device_token' => 'required|string',
            'punches' => 'required|array|min:1',
            'punches.*.emp_code' => 'required|string',
            'punches.*.punched_at' => 'required|date',
            'punches.*.direction' => 'nullable|in:in,out,break_start,break_end,auto',
        ]);

        $kiosk = KioskDevice::where('code', $data['kiosk_code'])->where('is_active', true)->first();
        if (! $kiosk || ! Hash::check($data['device_token'], $kiosk->device_token_hash)) {
            return response()->json(['message' => 'Unauthorized biometric device.'], 403);
        }

        $kiosk->update(['last_seen_at' => now()]);
        $accepted = 0;

        foreach ($data['punches'] as $item) {
            $emp = Employee::where('emp_code', $item['emp_code'])->first();
            if (! $emp) {
                continue;
            }
            $at = Carbon::parse($item['punched_at']);
            AttendancePunch::create([
                'employee_id' => $emp->id,
                'punched_at' => $at,
                'punch_date' => $at->toDateString(),
                'direction' => $item['direction'] ?? 'auto',
                'channel' => 'biometric',
                'work_location_id' => $kiosk->work_location_id,
                'kiosk_device_id' => $kiosk->id,
                'raw_payload' => $item,
                'status' => 'valid',
            ]);
            $calculation->recalculateDay($emp, $at);
            $accepted++;
        }

        return response()->json(['accepted' => $accepted]);
    }
}
