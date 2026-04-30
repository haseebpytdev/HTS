<x-guest-layout :title="__('Reset password')">
    <h1 class="h4 text-brand-navy fw-bold mb-2">{{ __('Reset password') }}</h1>
    <p class="small text-secondary mb-4">{{ __('Choose a new password for your customer account.') }}</p>

    <form method="POST" action="{{ route('customer.password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->token ?? request('token') }}">

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')
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

        <button type="submit" class="btn btn-brand-green text-white rounded-pill px-4 w-100 w-sm-auto">{{ __('Reset password') }}</button>
    </form>
</x-guest-layout>
