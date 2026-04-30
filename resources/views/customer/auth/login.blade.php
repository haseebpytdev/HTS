<x-guest-layout :title="__('Customer login')">
    <h1 class="h4 text-brand-navy fw-bold mb-2">{{ __('Customer login') }}</h1>
    <p class="small text-secondary mb-4">
        {{ __('View bookings, vouchers, and payments. Staff and agencies should use') }}
        <a href="{{ route('login') }}" class="text-brand-green text-decoration-none fw-semibold">{{ __('staff login') }}</a>.
    </p>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('customer.login') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="form-control @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember_customer" value="1">
            <label class="form-check-label small" for="remember_customer">{{ __('Remember me') }}</label>
        </div>

        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
            <a class="small text-brand-green text-decoration-none fw-semibold order-sm-2" href="{{ route('customer.password.request') }}">
                {{ __('Forgot password?') }}
            </a>
            <button type="submit" class="btn btn-brand-green text-white rounded-pill px-4 order-sm-1">{{ __('Log in') }}</button>
        </div>
    </form>

    <p class="small text-secondary mt-4 mb-0 pt-3 border-top">
        {{ __('No account?') }}
        <a href="{{ route('customer.register') }}" class="text-brand-green text-decoration-none fw-semibold">{{ __('Register') }}</a>
    </p>
</x-guest-layout>
