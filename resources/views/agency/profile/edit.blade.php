@extends('layouts.agency')

@section('title', 'Profile & Settings')

@section('agency-content')
    <h1 class="h4 mb-3">Profile & Settings</h1>

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

    <form method="POST" action="{{ route('agency.profile.update') }}" class="card shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">User Name</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">User Email</label><input class="form-control" name="email" value="{{ old('email', $user->email) }}" required></div>
            <div class="col-md-6"><label class="form-label">Agency Name</label><input class="form-control" name="agency_name" value="{{ old('agency_name', $user->agency?->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Agency Code</label><input class="form-control" name="agency_code" value="{{ old('agency_code', $user->agency?->code) }}" required></div>
            <div class="col-12"><button class="btn btn-success">Save Settings</button></div>
        </div>
    </form>
@endsection
