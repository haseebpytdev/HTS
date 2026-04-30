@extends('layouts.admin')

@section('title', 'Tenant Provider Access')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Tenant Provider Access - {{ $tenant->name }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.tenants.plans.edit', $tenant) }}" class="btn btn-sm btn-outline-secondary">Plan</a>
            <a href="{{ route('admin.tenants.modules.edit', $tenant) }}" class="btn btn-sm btn-outline-secondary">Modules</a>
            <a href="{{ route('admin.tenants.plans.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info py-2 small">
        Provider governance controls apply at runtime before adapter execution: operation toggles (search/pricing/booking),
        priority/fallback, module+connection health, and quota soft/hard limits (daily search/pricing, monthly booking).
    </div>

    <form method="POST" action="{{ route('admin.tenants.providers.update', $tenant) }}">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Enable</th>
                        <th>Operations</th>
                        <th>Priority</th>
                        <th>Fallback / Multi</th>
                        <th>Quota (Monthly/Daily)</th>
                        <th>Soft %</th>
                        <th>Hard</th>
                        <th>Alert</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $i => $row)
                        <tr>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][provider]" value="{{ $row['provider'] }}">
                                <input type="hidden" name="rows[{{ $i }}][plan_code]" value="{{ $row['plan_code'] ?? '' }}">
                                <div class="fw-semibold text-uppercase">{{ $row['provider'] }}</div>
                                @if(($row['provider'] ?? '') === 'sabre')
                                    <div class="small text-warning">Sabre BFM is sandbox search-only in this phase.</div>
                                @endif
                                <div class="small text-muted">
                                    Source: {{ ucfirst((string) ($row['source'] ?? 'plan_default')) }}
                                </div>
                            </td>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][is_enabled]" value="0">
                                <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][is_enabled]" value="1" @checked((bool) ($row['is_enabled'] ?? false))>
                            </td>
                            <td>
                                <label class="me-2">
                                    <input type="hidden" name="rows[{{ $i }}][can_search]" value="0">
                                    <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][can_search]" value="1" @checked((bool) ($row['can_search'] ?? false))>
                                    Search
                                </label>
                                <label class="me-2">
                                    <input type="hidden" name="rows[{{ $i }}][can_price]" value="0">
                                    <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][can_price]" value="1" @checked((bool) ($row['can_price'] ?? false)) @disabled(($row['provider'] ?? '') === 'sabre')>
                                    Pricing
                                </label>
                                <label>
                                    <input type="hidden" name="rows[{{ $i }}][can_book]" value="0">
                                    <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][can_book]" value="1" @checked((bool) ($row['can_book'] ?? false)) @disabled(($row['provider'] ?? '') === 'sabre')>
                                    Booking
                                </label>
                            </td>
                            <td>
                                <input type="number" min="1" max="9999" class="form-control form-control-sm"
                                       name="rows[{{ $i }}][priority_order]" value="{{ (int) ($row['priority_order'] ?? 100) }}">
                            </td>
                            <td>
                                <label class="d-block small mb-1">
                                    <input type="hidden" name="rows[{{ $i }}][allow_fallback]" value="0">
                                    <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][allow_fallback]" value="1" @checked((bool) ($row['allow_fallback'] ?? false))>
                                    Fallback
                                </label>
                                <label class="d-block small mb-0">
                                    <input type="hidden" name="rows[{{ $i }}][allow_multi_provider]" value="0">
                                    <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][allow_multi_provider]" value="1" @checked((bool) ($row['allow_multi_provider'] ?? false))>
                                    Multi-provider
                                </label>
                            </td>
                            <td>
                                <input type="number" min="0" class="form-control form-control-sm mb-1"
                                       name="rows[{{ $i }}][bookings_monthly_quota]"
                                       value="{{ (int) (($row['usage_quota_json']['bookings_monthly'] ?? 0)) }}"
                                       placeholder="Bookings M">
                                <input type="number" min="0" class="form-control form-control-sm"
                                       name="rows[{{ $i }}][searches_daily_quota]"
                                       value="{{ (int) (($row['usage_quota_json']['searches_daily'] ?? 0)) }}"
                                       placeholder="Searches D">
                            </td>
                            <td>
                                <input type="number" min="1" max="100" class="form-control form-control-sm"
                                       name="rows[{{ $i }}][soft_limit_percent]" value="{{ (int) ($row['soft_limit_percent'] ?? 80) }}">
                            </td>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][hard_limit_enforced]" value="0">
                                <input type="checkbox" class="form-check-input"
                                       name="rows[{{ $i }}][hard_limit_enforced]" value="1" @checked((bool) ($row['hard_limit_enforced'] ?? false))>
                            </td>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][overage_alert_enabled]" value="0">
                                <input type="checkbox" class="form-check-input"
                                       name="rows[{{ $i }}][overage_alert_enabled]" value="1" @checked((bool) ($row['overage_alert_enabled'] ?? true))>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
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

        <button type="submit" class="btn btn-primary mt-3">Save Provider Access</button>
    </form>
@endsection
