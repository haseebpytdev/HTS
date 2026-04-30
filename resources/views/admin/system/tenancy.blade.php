@extends('layouts.admin')

@section('title', 'Tenant scoping')

@section('admin-content')
    <h1 class="h4 mb-3">Tenant isolation (phase 2)</h1>
    <p class="text-muted small mb-4">
        When enabled, staff (except super admins) only query data for their <code>tenant_id</code>.
        Legacy installs stay unchanged until you turn this on. Default strict isolation is not forced.
    </p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('admin.system.tenancy.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="tenant_scoping_enabled" value="0">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="tenant_scoping_enabled" name="tenant_scoping_enabled" value="1"
                        {{ old('tenant_scoping_enabled', $tenantScopingEnabled ? '1' : '') === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="tenant_scoping_enabled">Enable opt-in tenant query scoping</label>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>
@endsection
