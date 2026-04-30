@extends('layouts.admin')

@section('title', 'Tenant Module Access')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Tenant Module Access - {{ $tenant->name }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.tenants.plans.edit', $tenant) }}" class="btn btn-sm btn-outline-secondary">Plan</a>
            <a href="{{ route('admin.tenants.providers.edit', $tenant) }}" class="btn btn-sm btn-outline-dark">Providers</a>
            <a href="{{ route('admin.tenants.plans.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.tenants.modules.update', $tenant) }}">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Module</th>
                        <th>Enable</th>
                        <th>Operations</th>
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
                                <input type="hidden" name="rows[{{ $i }}][module_code]" value="{{ $row['module_code'] }}">
                                <div class="fw-semibold">{{ $row['module_name'] }}</div>
                                <div class="small text-muted">{{ $row['service_type'] }} - {{ $row['module_code'] }}</div>
                            </td>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][is_enabled]" value="0">
                                <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][is_enabled]" value="1" @checked($row['is_enabled'])>
                            </td>
                            <td>
                                @foreach(['search', 'pricing', 'booking'] as $op)
                                    <label class="me-2">
                                        <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][allowed_operations][]" value="{{ $op }}" @checked(in_array($op, (array) $row['allowed_operations'], true))>
                                        {{ ucfirst($op) }}
                                    </label>
                                @endforeach
                            </td>
                            <td>
                                <input type="number" min="0" class="form-control form-control-sm mb-1" name="rows[{{ $i }}][bookings_monthly_quota]" value="{{ (int) ($row['usage_quota_json']['bookings_monthly'] ?? 0) }}" placeholder="Bookings M">
                                <input type="number" min="0" class="form-control form-control-sm" name="rows[{{ $i }}][searches_daily_quota]" value="{{ (int) ($row['usage_quota_json']['searches_daily'] ?? 0) }}" placeholder="Searches D">
                            </td>
                            <td><input type="number" min="1" max="100" class="form-control form-control-sm" name="rows[{{ $i }}][soft_limit_percent]" value="{{ (int) ($row['soft_limit_percent'] ?? 80) }}"></td>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][hard_limit_enforced]" value="0">
                                <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][hard_limit_enforced]" value="1" @checked($row['hard_limit_enforced'])>
                            </td>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][overage_alert_enabled]" value="0">
                                <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][overage_alert_enabled]" value="1" @checked($row['overage_alert_enabled'])>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Save Module Access</button>
    </form>
@endsection
