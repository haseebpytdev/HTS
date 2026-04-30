@extends('layouts.admin')

@section('title', 'Create Promo Code')

@section('admin-content')
    <h1 class="h4 mb-3">Create Promo Code</h1>
    <form method="POST" action="{{ route('admin.promo-codes.store') }}" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body">
            @include('admin.promo-codes.partials.form', ['promoCode' => null])
        </div>
        <div class="card-footer"><button class="btn btn-sm btn-primary">Create</button></div>
    </form>
@endsection
