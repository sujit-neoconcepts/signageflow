<?php

namespace App\Http\Requests\Auth;

use App\Models\Setting;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use App\Rules\Recaptcha;
use App\Models\SigninLog;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        $captcha_en = config('services.recaptcha.enabled', false);
        if ($captcha_en) {
            return [
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
                'captcha_token' => ['required', new Recaptcha()]
            ];
        } else {
            return [
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string']
            ];
        }
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $setting = Setting::where('slug', 'lock_user_duration')->get()->first();
            $lockPeriod = $setting && $setting->value ? (int) $setting->value : 120;
            RateLimiter::hit($this->throttleKey(), $lockPeriod);

            // log failed
            SigninLog::create(
                [
                    'email' => $this->input('email'),
                    'ip' => $this->ip(),
                    'msg' => 'Login Failed',
                    'userAgent' => $this->userAgent(),
                ]
            );
            SigninLog::signlogMail(
                [
                    'email' => $this->input('email'),
                    'ip' => $this->ip(),
                    'msg' => 'Login Failed',
                    'userAgent' => $this->userAgent(),
                ]
            );
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {

        $setting = Setting::where('slug', 'lock_user_attempt')->get()->first();
        $loginThrotel = $setting && $setting->value ? (int) $setting->value : 5;

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $loginThrotel)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')) . '|' . $this->ip());
    }
}
