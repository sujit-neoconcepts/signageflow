<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\UserEmailCode;
use Inertia\Inertia;
use App\Models\SigninLog;

use Illuminate\Support\Facades\Auth;

class TwoFAController extends Controller
{
    /**
     * Write code on Method
     *
     * @return response()
     */
    public function index()
    {
        $setting = Setting::where('slug', 'otp_reset_duration')->get()->first();
        $lockPeriod = $setting && $setting->value ? $setting->value : 120;
        return Inertia::render('Auth/2Fa', ['lockPeriod' => $lockPeriod]);
    }

    /**
     * Write code on Method
     *
     * @return response()
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required',
        ]);

        $find = UserEmailCode::where('user_id', auth()->user()->id)
            ->where('code', $request->code)
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->first();
        if (!is_null($find)) {
            Session::put('user_2fa', auth()->user()->id);

            SigninLog::create(
                [
                    'email' => Auth::user()->email,
                    'ip' => $request->ip(),
                    'msg' => 'Otp Success',
                    'userAgent' => $request->userAgent(),
                ]
            );

            return redirect()->route('dashboard');
        }

        // Otp failed
        SigninLog::create(
            [
                'email' => Auth::user()->email,
                'ip' => $request->ip(),
                'msg' => 'Otp Failed',
                'userAgent' => $request->userAgent(),
            ]
        );

        return back()->with(['message' => 'You entered wrong code.', 'msg_type' => 'danger']);
    }
    /**
     * Write code on Method
     *
     * @return response()
     */
    public function resend()
    {
        Auth::user()->generateCode();

        return back()->with(['message' => 'We re-sent code to  your email.', 'msg_type' => 'success']);
    }
}
