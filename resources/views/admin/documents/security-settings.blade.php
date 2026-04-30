@extends('layouts.admin')

@section('title', 'Document Security Settings')

@section('admin-content')
    <h1 class="h4 mb-3">Enterprise Document Security Controls</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.documents.security-settings.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="approval_required" value="0">
        @php
            $oldOrSetting = static fn (string $key, mixed $default = null) => old($key, $settings[$key] ?? $default);
        @endphp

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Upload Policy</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Allowed image MIME types (comma or new line separated)</label>
                    <textarea class="form-control" rows="4" name="allowed_image_mimes">{{ implode(PHP_EOL, (array) $oldOrSetting('allowed_image_mimes', [])) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Allowed image extensions (comma or new line separated)</label>
                    <textarea class="form-control" rows="4" name="allowed_image_extensions">{{ implode(PHP_EOL, (array) $oldOrSetting('allowed_image_extensions', [])) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Allowed document MIME types (comma or new line separated)</label>
                    <textarea class="form-control" rows="4" name="allowed_document_mimes">{{ implode(PHP_EOL, (array) $oldOrSetting('allowed_document_mimes', [])) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Allowed document extensions (comma or new line separated)</label>
                    <textarea class="form-control" rows="4" name="allowed_document_extensions">{{ implode(PHP_EOL, (array) $oldOrSetting('allowed_document_extensions', [])) }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max upload file size (bytes)</label>
                    <input class="form-control" type="number" name="max_file_size_bytes" value="{{ (int) $oldOrSetting('max_file_size_bytes', 10 * 1024 * 1024) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max scanner payload size (bytes)</label>
                    <input class="form-control" type="number" name="max_scan_file_size_bytes" value="{{ (int) $oldOrSetting('max_scan_file_size_bytes', 20 * 1024 * 1024) }}">
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Scan and Quarantine Policy</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Scan mode</label>
                    <select name="scan_mode" class="form-select">
                        <option value="disabled" @selected($oldOrSetting('scan_mode', 'stub') === 'disabled')>Disabled</option>
                        <option value="stub" @selected($oldOrSetting('scan_mode', 'stub') === 'stub')>Stub</option>
                        <option value="real" @selected($oldOrSetting('scan_mode', 'stub') === 'real')>Real</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Scan provider</label>
                    <select name="scan_provider" class="form-select">
                        <option value="stub" @selected($oldOrSetting('scan_provider', 'stub') === 'stub')>Stub</option>
                        <option value="clamav" @selected($oldOrSetting('scan_provider', 'stub') === 'clamav')>ClamAV</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Quarantine behavior</label>
                    <select name="quarantine_behavior" class="form-select">
                        <option value="block_all" @selected($oldOrSetting('quarantine_behavior', 'block_all') === 'block_all')>Block quarantined documents for all users</option>
                        <option value="admin_review_only" @selected($oldOrSetting('quarantine_behavior', 'block_all') === 'admin_review_only')>Allow admin-only review of quarantined documents</option>
                        <option value="allow_all" @selected($oldOrSetting('quarantine_behavior', 'block_all') === 'allow_all')>Allow all downloads (dangerous)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rescan policy</label>
                    <select name="rescan_policy" class="form-select">
                        <option value="manual_only" @selected($oldOrSetting('rescan_policy', 'manual_only') === 'manual_only')>Manual only</option>
                        <option value="auto_on_failed" @selected($oldOrSetting('rescan_policy', 'manual_only') === 'auto_on_failed')>Auto-rescan failed scans</option>
                        <option value="auto_on_failed_or_suspicious" @selected($oldOrSetting('rescan_policy', 'manual_only') === 'auto_on_failed_or_suspicious')>Auto-rescan failed or suspicious scans</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max automatic rescan attempts</label>
                    <input class="form-control" type="number" name="rescan_max_attempts" min="0" max="10" value="{{ (int) $oldOrSetting('rescan_max_attempts', 1) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Download restriction</label>
                    <select name="download_restriction" class="form-select">
                        <option value="scan_clean_only" @selected($oldOrSetting('download_restriction', 'scan_clean_only') === 'scan_clean_only')>Clean scan required</option>
                        <option value="validated_and_clean" @selected($oldOrSetting('download_restriction', 'scan_clean_only') === 'validated_and_clean')>Validated + clean required for all users</option>
                        <option value="admin_only_until_approved" @selected($oldOrSetting('download_restriction', 'scan_clean_only') === 'admin_only_until_approved')>Customers blocked until approved + clean</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Document approval requirement</label>
                    <select name="approval_requirement" class="form-select">
                        <option value="none" @selected($oldOrSetting('approval_requirement', 'all_documents') === 'none')>No scan-gated approval requirement</option>
                        <option value="customer_visible_only" @selected($oldOrSetting('approval_requirement', 'all_documents') === 'customer_visible_only')>Only customer-visible documents require clean scan before approval</option>
                        <option value="all_documents" @selected($oldOrSetting('approval_requirement', 'all_documents') === 'all_documents')>All documents require clean scan before approval</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" name="approval_required" id="approval_required" @checked((bool) $oldOrSetting('approval_required', true))>
                        <label class="form-check-label" for="approval_required">Require document approval workflow</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Lifecycle Policy</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Retention days</label>
                    <input class="form-control" type="number" name="retention_days" value="{{ (int) $oldOrSetting('retention_days', 365) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Archive default</label>
                    <select name="archive_default" class="form-select">
                        <option value="soft_delete" @selected($oldOrSetting('archive_default', 'soft_delete') === 'soft_delete')>Soft-delete archive</option>
                        <option value="archive" @selected($oldOrSetting('archive_default', 'soft_delete') === 'archive')>Archive bucket (policy only)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Retention purge mode</label>
                    <select name="retention_purge_mode" class="form-select">
                        <option value="archive_then_purge" @selected($oldOrSetting('retention_purge_mode', 'archive_then_purge') === 'archive_then_purge')>Archive then purge by retention</option>
                        <option value="hard_delete" @selected($oldOrSetting('retention_purge_mode', 'archive_then_purge') === 'hard_delete')>Hard delete after retention</option>
                        <option value="keep_archived_only" @selected($oldOrSetting('retention_purge_mode', 'archive_then_purge') === 'keep_archived_only')>Keep archived indefinitely</option>
                    </select>
                </div>
            </div>
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

        <button type="submit" class="btn btn-primary">Save Document Security Settings</button>
    </form>
@endsection
