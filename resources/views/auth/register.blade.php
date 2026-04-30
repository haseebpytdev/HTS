<x-guest-layout :title="__('Staff registration')">
    <h1 class="h4 text-brand-navy fw-bold mb-2">{{ __('Staff registration') }}</h1>
    <p class="small text-secondary mb-4">{{ __('Create an internal staff account. Customer travellers should') }}
        <a href="{{ route('customer.register') }}" class="text-brand-green text-decoration-none fw-semibold">{{ __('register here') }}</a>.
    </p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label as-label">{{ __('Name') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="form-control as-input @error('name') is-invalid @enderror">
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label as-label">{{ __('Email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                   class="form-control as-input @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label as-label">{{ __('Password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                   class="form-control as-input @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label as-label">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="form-control as-input @error('password_confirmation') is-invalid @enderror">
            @error('password_confirmation')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
            <a href="{{ route('login') }}" class="small text-secondary text-decoration-none">{{ __('Already registered?') }}</a>
            <button type="submit" class="btn btn-brand-green text-white rounded-pill px-4">{{ __('Register') }}</button>
        </div>
    </form>
</x-guest-layout>
