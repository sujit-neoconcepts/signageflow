<?php

namespace App\Http\Middleware;

use Closure;
use Session;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Check2FA
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // If user is not authenticated, let the auth middleware handle it
        if (! $request->user()) {
            return $next($request);
        }

        // If user is authenticated but needs 2FA
        if ($request->user()->twofa && !Session::has('user_2fa')) {
            if(Auth::viaRemember()) {
                Session::put('user_2fa', auth()->user()->id);
                return $next($request);
            }
            return redirect()->route('2fa.index');
        }

        return $next($request);
    }
}
