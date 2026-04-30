<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->session()->put('auth.login_at', now()->timestamp);

        $guard = $request->authenticatedGuard();
        if ($guard === 'customer') {
            return redirect()->intended(route('customer.dashboard', absolute: false));
        }

        $role = Auth::guard('web')->user()?->role;
        $roleValue = $role instanceof UserRole ? $role->value : (string) $role;

        return match ($roleValue) {
            'super_admin', 'admin', 'sales_operator' => redirect()->intended(route('admin.dashboard', absolute: false)),
            'agency_user' => redirect()->intended(route('agency.dashboard', absolute: false)),
            default => redirect()->intended(route('frontend.home', absolute: false)),
        };
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        Auth::guard('customer')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
