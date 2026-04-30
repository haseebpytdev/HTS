@extends('layouts.admin')

@section('title', 'Homepage content')

@section('admin-content')
    <h1 class="h4 mb-3">Homepage &amp; banners</h1>
    <p class="text-muted small mb-3">Edit structured sections for the public homepage without code changes.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>Key</th>
                <th>Label</th>
                <th>Active</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach($blocks as $block)
                <tr>
                    <td><code>{{ $block->block_key }}</code></td>
                    <td>{{ $block->label }}</td>
                    <td>{{ $block->is_active ? 'Yes' : 'No' }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.content-blocks.edit', $block) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
