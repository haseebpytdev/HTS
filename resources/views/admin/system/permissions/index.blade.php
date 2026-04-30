@extends('layouts.admin')

@section('title', 'Permission Matrix')

@section('admin-content')
    <h1 class="h4 mb-2">Permission matrix</h1>
    <p class="text-muted small mb-3">
        Runtime authorization remains config-based. Changes here are proposals for audit/export and developer apply.
    </p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="mb-3 d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.system.permissions.export') }}">Export config-ready proposal</a>
    </div>

    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Current effective permissions</th>
                    <th>Proposed override</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($draftMatrix as $role => $permissions)
                    <tr>
                        <td><code>{{ $role }}</code></td>
                        <td>{{ count($permissions) }}</td>
                        <td>
                            @if(array_key_exists($role, $proposedMatrix))
                                <span class="badge text-bg-warning">Yes</span>
                            @else
                                <span class="badge text-bg-secondary">No</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.system.permissions.edit', $role) }}">
                                Edit role
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h6 mb-2">Known permission keys</h2>
            <div class="small text-muted">
                @foreach($allPermissions as $p)
                    <div><code>{{ $p }}</code></div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
