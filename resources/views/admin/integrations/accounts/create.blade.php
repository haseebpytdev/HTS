@extends('layouts.admin')

@section('title', 'Add supplier account')

@section('admin-content')
    <h1 class="h4 mb-3">Add supplier account</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @php($form = ['test' => [], 'production' => []])
            @include('admin.integrations.accounts._form')
        </div>
    </div>
@endsection
