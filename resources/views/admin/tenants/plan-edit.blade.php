@extends('layouts.admin')

@section('title', 'Edit Tenant Plan')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Tenant Plan - {{ $tenant->name }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.tenants.modules.edit', $tenant) }}" class="btn btn-sm btn-outline-secondary">Modules</a>
            <a href="{{ route('admin.tenants.providers.edit', $tenant) }}" class="btn btn-sm btn-outline-dark">Providers</a>
            <a href="{{ route('admin.tenants.plans.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.tenants.plans.update', $tenant) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="hard_limit_enforced" value="0">
        <input type="hidden" name="overage_alert_enabled" value="0">

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Plan Tier</label>
                        <select name="plan_tier" class="form-select">
                            @foreach(['basic', 'growth', 'pro', 'enterprise'] as $tier)
                                <option value="{{ $tier }}" @selected(($tenant->plan_tier ?? 'basic') === $tier)>{{ ucfirst($tier) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Monthly Bookings Quota</label>
                        <input type="number" min="0" name="bookings_monthly_quota" class="form-control" value="{{ (int) (($tenant->usage_quota_json['bookings_monthly'] ?? 0)) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Daily Searches Quota</label>
                        <input type="number" min="0" name="searches_daily_quota" class="form-control" value="{{ (int) (($tenant->usage_quota_json['searches_daily'] ?? 0)) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Soft Limit Percent</label>
                        <input type="number" min="1" max="100" name="soft_limit_percent" class="form-control" value="{{ (int) ($tenant->soft_limit_percent ?? 80) }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" name="hard_limit_enforced" id="hard_limit_enforced" @checked((bool) ($tenant->hard_limit_enforced ?? false))>
                            <label class="form-check-label" for="hard_limit_enforced">Hard limits enforced</label>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" name="overage_alert_enabled" id="overage_alert_enabled" @checked((bool) ($tenant->overage_alert_enabled ?? true))>
                            <label class="form-check-label" for="overage_alert_enabled">Overage alerts enabled</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger mt-3">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <button type="submit" class="btn btn-primary mt-3">Save Plan Governance</button>
    </form>
@endsection
