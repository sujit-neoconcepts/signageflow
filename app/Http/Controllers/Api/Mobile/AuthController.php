<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $hasHrmsEmployee = class_exists(\SignageFlow\Hrms\Models\Employee::class)
            && \SignageFlow\Hrms\Models\Employee::active()->where('user_id', $user->id)->exists();

        if (! $user->can('task_MyTasksList') && ! $user->can('expense_list') && ! $hasHrmsEmployee && ! $user->can('hrmsAttendance_self') && ! $user->can('hrmsLeave_self')) {
            abort(403, 'You do not have mobile app access.');
        }

        $user->tokens()->where('name', 'mobile-app')->delete();
        $token = $user->createToken('mobile-app', ['mobile:tasks'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => [
                    'expense_back_date_entry' => $user->can('expense_back_date_entry'),
                    'hrms_enabled' => $hasHrmsEmployee || $user->can('hrmsAttendance_self') || $user->can('hrmsLeave_self'),
                    'hrms_attendance' => $hasHrmsEmployee || $user->can('hrmsAttendance_self'),
                    'hrms_leave' => $hasHrmsEmployee || $user->can('hrmsLeave_self'),
                    'hrms_payslips' => $hasHrmsEmployee || $user->can('hrmsPayroll_selfPayslip'),
                ],
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
