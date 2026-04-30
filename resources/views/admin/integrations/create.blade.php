@extends('layouts.admin')

@section('title', 'Create Integration Connection')

@section('admin-content')
    <h1 class="h4 mb-3">Create Integration Connection</h1>
    <p class="text-muted small mb-3">For Sabre sandbox setup, enter Developer Hub User ID and Password in the credentials section, and keep Search enabled only for this phase.</p>
    @include('admin.integrations._form', ['isCreate' => true])
@endsection
