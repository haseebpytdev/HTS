@extends('layouts.admin')

@section('title', 'Airport Directory')

@section('admin-content')
    <h1 class="h4 mb-3">Airport Directory (Local JSON)</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Current dataset status</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="small text-muted">Airport rows</div>
                    <div class="fw-semibold">{{ (int) ($meta['count'] ?? 0) }}</div>
                </div>
                <div class="col-md-8">
                    <div class="small text-muted">Last updated</div>
                    <div class="fw-semibold">
                        @if(! empty($meta['updated_at']))
                            {{ \Illuminate\Support\Carbon::createFromTimestamp((int) $meta['updated_at'])->toDayDateTimeString() }}
                        @else
                            Not available
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.system.airports.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Upload new airport JSON file</strong></div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Upload a JSON file containing airport entries with keys:
                    <code>iata</code>, <code>airport</code>, <code>city</code>, <code>country</code>.
                </p>
                <input type="file" class="form-control" name="airports_file" accept=".json,application/json" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Import Airport File</button>
    </form>
@endsection

