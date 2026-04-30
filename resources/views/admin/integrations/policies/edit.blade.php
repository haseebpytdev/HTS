@extends('layouts.admin')

@section('title', 'Edit integration policy')

@section('admin-content')
    <h1 class="h4 mb-3">Integration policy: {{ $tenant->name }}</h1>
    <p class="text-muted small">Plan tier: <strong>{{ strtoupper($tenant->plan_tier ?? 'basic') }}</strong>. Save overrides only when needed.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('admin.integrations.policies.update', $tenant) }}">
                @csrf
                @method('PUT')

                @php($allowed = old('allowed_providers', $policy->allowed_providers ?? []))
                <div class="mb-3">
                    <label class="form-label">Allowed providers</label>
                    <div>
                        @foreach([
                            \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE => 'AMADEUS SELF SERVICE',
                            'sabre' => 'SABRE',
                            'travelport' => 'TRAVELPORT',
                            'iati' => 'IATI',
                            'duffel' => 'DUFFEL',
                        ] as $provider => $label)
                            @php($checked = in_array($provider, $allowed, true) || ($provider === \App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::CODE && in_array(\App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider::LEGACY_CODE, $allowed, true)))
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="allowed_providers[]" value="{{ $provider }}" id="prov_{{ $provider }}"
                                    @checked($checked)>
                                <label class="form-check-label" for="prov_{{ $provider }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                @php($perm = $policy->provider_permissions ?? [])
                <div class="mb-3">
                    <label class="form-label">Operation access</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input type="hidden" name="search_enabled" value="0">
                            <input class="form-check-input" type="checkbox" name="search_enabled" value="1" id="search_enabled"
                                @checked((bool) old('search_enabled', $perm['search'] ?? false))>
                            <label class="form-check-label" for="search_enabled">Search</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="hidden" name="pricing_enabled" value="0">
                            <input class="form-check-input" type="checkbox" name="pricing_enabled" value="1" id="pricing_enabled"
                                @checked((bool) old('pricing_enabled', $perm['pricing'] ?? false))>
                            <label class="form-check-label" for="pricing_enabled">Pricing</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="hidden" name="booking_enabled" value="0">
                            <input class="form-check-input" type="checkbox" name="booking_enabled" value="1" id="booking_enabled"
                                @checked((bool) old('booking_enabled', $perm['booking'] ?? false))>
                            <label class="form-check-label" for="booking_enabled">Booking</label>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="hidden" name="allow_multi_provider" value="0">
                            <input class="form-check-input" type="checkbox" name="allow_multi_provider" value="1" id="allow_multi_provider"
                                @checked((bool) old('allow_multi_provider', $policy->allow_multi_provider ?? false))>
                            <label class="form-check-label" for="allow_multi_provider">Allow multi-provider search</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="hidden" name="allow_fallback" value="0">
                            <input class="form-check-input" type="checkbox" name="allow_fallback" value="1" id="allow_fallback"
                                @checked((bool) old('allow_fallback', $policy->allow_fallback ?? false))>
                            <label class="form-check-label" for="allow_fallback">Allow fallback</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Provider priority order</label>
                        <input type="text" class="form-control" name="provider_priority"
                               placeholder="travelport,sabre,amadeus_self_service,iati,duffel"
                               value="{{ old('provider_priority', isset($policy) ? implode(',', $policy->provider_priority ?? []) : '') }}">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save policy</button>
                <a href="{{ route('admin.integrations.policies.index') }}" class="btn btn-light">Back</a>
            </form>
        </div>
    </div>
@endsection
