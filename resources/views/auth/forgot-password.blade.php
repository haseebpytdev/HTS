<x-guest-layout :title="__('Forgot password')">
    <h1 class="h4 text-brand-navy fw-bold mb-2">{{ __('Forgot password') }}</h1>
    <p class="small text-secondary mb-4">
        {{ __('Enter the email on your staff account and we will send a reset link.') }}
    </p>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label as-label">{{ __('Email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="form-control as-input @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex flex-column flex-sm-row gap-3 align-items-stretch align-items-sm-center justify-content-between">
            <a href="{{ route('login') }}" class="small text-secondary text-decoration-none">{{ __('Back to login') }}</a>
            <button type="submit" class="btn btn-brand-green text-white rounded-pill px-4">{{ __('Email reset link') }}</button>
        </div>
    </form>
</x-guest-layout>
