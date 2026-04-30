<x-guest-layout :title="__('Confirm password')">
    <h1 class="h4 text-brand-navy fw-bold mb-2">{{ __('Confirm password') }}</h1>
    <p class="small text-secondary mb-4">{{ __('This is a secure area. Please confirm your password before continuing.') }}</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="mb-4">
            <label for="password" class="form-label as-label">{{ __('Password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="form-control as-input @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-brand-green text-white rounded-pill px-4">{{ __('Confirm') }}</button>
    </form>
</x-guest-layout>
