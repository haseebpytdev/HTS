@extends('layouts.admin')

@section('title', 'Settings')

@section('admin-content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Operational Settings</h1>
            <div class="small text-muted">System-level controls are grouped by domain for safer admin updates.</div>
        </div>
        <a href="{{ route('admin.settings.section', ['section' => 'general']) }}" class="btn btn-primary btn-sm">Open Settings Sections</a>
    </div>

    <div class="row g-3">
        @foreach([
            ['key' => 'general', 'title' => 'General', 'desc' => 'Site identity and support channels'],
            ['key' => 'branding', 'title' => 'Branding', 'desc' => 'Logo, colors, and visual defaults'],
            ['key' => 'localization', 'title' => 'Localization', 'desc' => 'Default currency and language'],
            ['key' => 'integrations', 'title' => 'Integrations', 'desc' => 'Provider defaults and fallback behavior'],
            ['key' => 'tax_pricing', 'title' => 'Tax & Pricing', 'desc' => 'Global tax and markup defaults'],
            ['key' => 'payments', 'title' => 'Payments', 'desc' => 'Gateway and wallet controls'],
            ['key' => 'notifications', 'title' => 'Notifications', 'desc' => 'Email/SMS toggles'],
            ['key' => 'feature_flags', 'title' => 'Feature Flags', 'desc' => 'Frontend service visibility controls'],
            ['key' => 'maintenance', 'title' => 'Maintenance', 'desc' => 'Platform maintenance flags'],
        ] as $card)
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="fw-semibold">{{ $card['title'] }}</div>
                        <div class="small text-muted mb-2">{{ $card['desc'] }}</div>
                        <a href="{{ route('admin.settings.section', ['section' => $card['key']]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
