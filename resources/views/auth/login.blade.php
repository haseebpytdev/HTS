<x-guest-layout :title="__('Login')">
    <h1 class="h4 text-brand-navy fw-bold mb-2">{{ __('Login') }}</h1>
    <p class="small text-secondary mb-4">
        {{ __('Sign in with your email or username. You will be redirected based on your access level.') }}
        <a href="{{ route('customer.register') }}" class="text-brand-green text-decoration-none fw-semibold">{{ __('Sign up') }}</a>
    </p>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="login" class="form-label as-label">{{ __('Email or Username') }}</label>
            <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username"
                   class="form-control as-input @error('login') is-invalid @enderror">
            @error('login')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label as-label">{{ __('Password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="form-control as-input @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember_me" value="1">
            <label class="form-check-label small" for="remember_me">{{ __('Remember me') }}</label>
        </div>

        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
            @if (Route::has('password.request'))
                <a class="small text-brand-green text-decoration-none fw-semibold order-sm-2" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
            <button type="submit" class="btn btn-brand-green text-white rounded-pill px-4 order-sm-1">{{ __('Log in') }}</button>
        </div>
    </form>
</x-guest-layout>
