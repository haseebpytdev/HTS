<x-guest-layout :title="__('Customer registration')">
    <h1 class="h4 text-brand-navy fw-bold mb-2">{{ __('Create account') }}</h1>
    <p class="small text-secondary mb-4">
        {{ __('Register to view bookings and pay online. Staff should use') }}
        <a href="{{ route('register') }}" class="text-brand-green text-decoration-none fw-semibold">{{ __('staff registration') }}</a>.
    </p>

    <form method="POST" action="{{ route('customer.register') }}">
        @csrf

        <div class="row g-3">
            <div class="col-sm-6">
                <label for="first_name" class="form-label">{{ __('First name') }}</label>
                <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required
                       class="form-control @error('first_name') is-invalid @enderror">
                @error('first_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-sm-6">
                <label for="last_name" class="form-label">{{ __('Last name') }}</label>
                <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                       class="form-control @error('last_name') is-invalid @enderror">
                @error('last_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3 mt-1">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">{{ __('Phone (optional)') }}</label>
            <input id="phone" type="text" name="phone" value="{{ old('phone') }}"
                   class="form-control @error('phone') is-invalid @enderror">
            @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="form-control">
        </div>

        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
            <a href="{{ route('customer.login') }}" class="small text-secondary text-decoration-none">{{ __('Already registered?') }}</a>
            <button type="submit" class="btn btn-brand-green text-white rounded-pill px-4">{{ __('Register') }}</button>
        </div>
    </form>
</x-guest-layout>
