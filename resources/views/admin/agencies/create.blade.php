@extends('layouts.admin')

@section('title', 'Create Agency')

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Create Agency</h1>
        <small class="text-muted">Add a new agency record.</small>
    </div>

    <x-admin.agency-form
        :action="route('admin.agencies.store')"
        method="POST"
        submit-label="Create Agency"
    />
@endsection
