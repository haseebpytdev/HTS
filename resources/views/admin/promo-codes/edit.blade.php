@extends('layouts.admin')

@section('title', 'Edit Promo Code')

@section('admin-content')
    <h1 class="h4 mb-3">Edit Promo Code</h1>
    <form method="POST" action="{{ route('admin.promo-codes.update', $promoCode) }}" class="card border-0 shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body">
            @include('admin.promo-codes.partials.form', ['promoCode' => $promoCode])
        </div>
        <div class="card-footer"><button class="btn btn-sm btn-primary">Save</button></div>
    </form>
@endsection
