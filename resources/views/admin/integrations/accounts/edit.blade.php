@extends('layouts.admin')

@section('title', 'Edit supplier account')

@section('admin-content')
    <h1 class="h4 mb-3">Edit supplier account</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="mb-3">
                @foreach(['test' => 'Sandbox', 'production' => 'Production'] as $env => $label)
                    @php($c = $connectionStatus[$env] ?? null)
                    @if($c)
                        <div class="small mb-1">
                            <strong>{{ $label }} status:</strong>
                            <span class="badge {{ $c->last_tested_status === 'ok' ? 'bg-success' : ($c->last_tested_status === 'failed' ? 'bg-danger' : 'bg-secondary') }}">
                                {{ $c->last_tested_status ? strtoupper($c->last_tested_status) : 'N/A' }}
                            </span>
                            @if($c->last_checked_at)
                                <span class="text-muted">last checked: {{ $c->last_checked_at->toDateTimeString() }}</span>
                            @endif
                            @if($c->last_success_at)
                                <span class="text-muted">| last success: {{ $c->last_success_at->toDateTimeString() }}</span>
                            @endif
                            @if($c->last_failure_reason)
                                <div class="text-danger">Failure: {{ $c->last_failure_reason }}</div>
                            @endif
                            <form method="post" action="{{ route('admin.integrations.accounts.test-connection') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="connection_id" value="{{ $c->id }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary mt-1">Test {{ $label }} Connection</button>
                            </form>
                        </div>
                    @endif
                @endforeach
            </div>
            @include('admin.integrations.accounts._form')
        </div>
    </div>
@endsection
