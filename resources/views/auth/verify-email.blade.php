<x-guest-layout :title="__('Verify email')">
    <h1 class="h4 text-brand-navy fw-bold mb-2">{{ __('Verify your email') }}</h1>
    <p class="small text-secondary mb-4">
        {{ __('Thanks for signing up. Please verify your email using the link we sent. If you did not receive it, we can send another.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success small py-2 mb-3" role="alert">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-brand-green text-white rounded-pill px-4">{{ __('Resend verification email') }}</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-link text-secondary text-decoration-none p-0 small">{{ __('Log out') }}</button>
        </form>
    </div>
</x-guest-layout>
