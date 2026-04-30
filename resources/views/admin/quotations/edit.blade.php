@extends('layouts.admin')

@section('title', 'Edit Quotation')

@section('admin-content')
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 mb-0">Edit Quotation {{ $quotation->quote_number }}</h1>
            <small class="text-muted">Recalculate and update quotation totals.</small>
        </div>
        <a href="{{ route('admin.quotations.show', $quotation) }}" class="btn btn-outline-secondary">Back</a>
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

    <form method="POST" action="{{ route('admin.quotations.update', $quotation) }}">
        @csrf
        @method('PUT')
        @include('admin.quotations._form', ['submitLabel' => 'Update Quotation'])
    </form>
@endsection
