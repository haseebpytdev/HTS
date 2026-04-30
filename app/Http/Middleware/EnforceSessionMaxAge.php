<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionMaxAge
{
    private const MAX_SECONDS = 21600; // 6 hours

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (! Auth::guard('web')->check() && ! Auth::guard('customer')->check()) {
            return $next($request);
        }

        $loginAt = (int) $request->session()->get('auth.login_at', 0);
        if ($loginAt <= 0) {
            $request->session()->put('auth.login_at', now()->timestamp);

            return $next($request);
        }

        if ((now()->timestamp - $loginAt) <= self::MAX_SECONDS) {
            return $next($request);
        }

        $isCustomer = Auth::guard('customer')->check();
        if ($isCustomer) {
            Auth::guard('customer')->logout();
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirectTo = $isCustomer ? route('customer.login') : route('login');

        return redirect($redirectTo)->with('status', 'Your session expired after 6 hours. Please sign in again.');
    }
}
