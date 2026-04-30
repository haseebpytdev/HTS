@extends('layouts.admin')

@section('title', 'Edit block')

@section('admin-content')
    <h1 class="h4 mb-2">{{ $block->label }}</h1>
    <p class="text-muted small mb-3"><code>{{ $block->block_key }}</code></p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.content-blocks.update', $block) }}" enctype="multipart/form-data" class="bg-white p-3 rounded shadow-sm">
        @csrf
        @method('PUT')

        @if($block->block_key === 'home.hero')
            @include('admin.content-blocks.partials.home_hero', ['block' => $block])
        @elseif($block->block_key === 'home.why_us')
            @include('admin.content-blocks.partials.home_why_us', ['block' => $block])
        @elseif($block->block_key === 'home.group_tickets')
            @include('admin.content-blocks.partials.home_group_tickets', ['block' => $block])
        @elseif($block->block_key === 'home.deals')
            @include('admin.content-blocks.partials.home_deals', ['block' => $block])
        @else
            <div class="mb-2">
                <label class="form-label">Payload (JSON)</label>
                <textarea name="payload_json" rows="12" class="form-control font-monospace small" required>{{ old('payload_json', json_encode($block->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) }}</textarea>
            </div>
        @endif

        <div class="form-check mb-3">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="ix" @checked(old('is_active', $block->is_active))>
            <label class="form-check-label" for="ix">Active</label>
        </div>

        <button class="btn btn-primary">Save</button>
        <a href="{{ route('admin.content-blocks.index') }}" class="btn btn-link">Back</a>
    </form>
@endsection
