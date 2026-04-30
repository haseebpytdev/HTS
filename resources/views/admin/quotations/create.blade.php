@extends('layouts.admin')

@section('title', 'Create Quotation')

@section('admin-content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Create Umrah Quotation</h1>
        <small class="text-muted">Use pricing inputs and save calculated totals.</small>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.quotations.store') }}">
        @csrf
        @include('admin.quotations._form', ['submitLabel' => 'Save Quotation'])
    </form>
@endsection
