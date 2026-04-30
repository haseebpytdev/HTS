@extends('layouts.admin')

@section('title', 'Edit Permission Role')

@section('admin-content')
    <h1 class="h4 mb-2">Role editor: <code>{{ $role }}</code></h1>
    <p class="text-muted small mb-3">
        This saves a proposal only. Runtime still uses <code>config/permissions.php</code>.
    </p>

    @if($isProposedOverride)
        <div class="alert alert-warning py-2">This role currently has a proposed override.</div>
    @endif

    <form method="post" action="{{ route('admin.system.permissions.update', $role) }}" class="card border-0 shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="row">
                @foreach($allPermissions as $permission)
                    <div class="col-md-6 mb-1">
                        <label class="form-check-label">
                            <input
                                type="checkbox"
                                class="form-check-input me-1"
                                name="permissions[]"
                                value="{{ $permission }}"
                                {{ in_array($permission, old('permissions', $currentPermissions), true) ? 'checked' : '' }}
                            >
                            <code>{{ $permission }}</code>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary" type="submit">Save proposal</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.system.permissions.index') }}">Back</a>
        </div>
    </form>
@endsection
