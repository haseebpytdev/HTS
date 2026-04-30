<?php

namespace App\Services\Documents;

use App\Services\System\SystemSettingsService;

final class DocumentSecuritySettingsService
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function policy(): array
    {
        $configImageMimes = (array) config('documents.upload.allowlists.images.mimes', ['image/jpeg', 'image/png', 'image/webp']);
        $configImageExt = (array) config('documents.upload.allowlists.images.extensions', ['jpg', 'jpeg', 'png', 'webp']);
        $configDocMimes = (array) config('documents.upload.allowlists.documents.mimes', ['application/pdf']);
        $configDocExt = (array) config('documents.upload.allowlists.documents.extensions', ['pdf']);

        return [
            'allowed_image_mimes' => $this->settings->getArray('documents.allowed_image_mimes', $configImageMimes, ['scope' => 'platform', 'category' => 'documents']),
            'allowed_image_extensions' => $this->settings->getArray('documents.allowed_image_extensions', $configImageExt, ['scope' => 'platform', 'category' => 'documents']),
            'allowed_document_mimes' => $this->settings->getArray('documents.allowed_document_mimes', $configDocMimes, ['scope' => 'platform', 'category' => 'documents']),
            'allowed_document_extensions' => $this->settings->getArray('documents.allowed_document_extensions', $configDocExt, ['scope' => 'platform', 'category' => 'documents']),
            'max_file_size_bytes' => $this->settings->getInt(
                'documents.max_file_size_bytes',
                (int) config('documents.upload.max_file_size_bytes', 10 * 1024 * 1024),
                ['scope' => 'platform', 'category' => 'documents']
            ),
            'max_scan_file_size_bytes' => $this->settings->getInt(
                'documents.max_scan_file_size_bytes',
                (int) config('documents.scanning.max_file_size_bytes', 20 * 1024 * 1024),
                ['scope' => 'platform', 'category' => 'documents']
            ),
            'scan_mode' => $this->settings->getString('documents.scan_mode', (string) config('documents.scanning.mode', 'stub'), ['scope' => 'platform', 'category' => 'documents']) ?? 'stub',
            'scan_provider' => $this->settings->getString('documents.scan_provider', (string) config('documents.scanning.provider', 'stub'), ['scope' => 'platform', 'category' => 'documents']) ?? 'stub',
            'quarantine_behavior' => $this->settings->getString('documents.quarantine_behavior', 'block_all', ['scope' => 'platform', 'category' => 'documents']) ?? 'block_all',
            'rescan_policy' => $this->settings->getString('documents.rescan_policy', 'manual_only', ['scope' => 'platform', 'category' => 'documents']) ?? 'manual_only',
            'rescan_max_attempts' => $this->settings->getInt('documents.rescan_max_attempts', 1, ['scope' => 'platform', 'category' => 'documents']),
            'retention_days' => $this->settings->getInt('documents.retention_days', 365, ['scope' => 'platform', 'category' => 'documents']),
            'retention_purge_mode' => $this->settings->getString('documents.retention_purge_mode', 'archive_then_purge', ['scope' => 'platform', 'category' => 'documents']) ?? 'archive_then_purge',
            'archive_default' => $this->settings->getString('documents.archive_default', 'soft_delete', ['scope' => 'platform', 'category' => 'documents']) ?? 'soft_delete',
            'download_restriction' => $this->settings->getString('documents.download_restriction', 'scan_clean_only', ['scope' => 'platform', 'category' => 'documents']) ?? 'scan_clean_only',
            'approval_requirement' => $this->approvalRequirement(),
            'approval_required' => $this->settings->getBool('documents.approval_required', true, ['scope' => 'platform', 'category' => 'documents']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function savePolicy(array $data): void
    {
        $this->settings->setMany([
            'documents.allowed_image_mimes' => array_values((array) ($data['allowed_image_mimes'] ?? [])),
            'documents.allowed_image_extensions' => array_values((array) ($data['allowed_image_extensions'] ?? [])),
            'documents.allowed_document_mimes' => array_values((array) ($data['allowed_document_mimes'] ?? [])),
            'documents.allowed_document_extensions' => array_values((array) ($data['allowed_document_extensions'] ?? [])),
            'documents.max_file_size_bytes' => (int) ($data['max_file_size_bytes'] ?? 10 * 1024 * 1024),
            'documents.max_scan_file_size_bytes' => (int) ($data['max_scan_file_size_bytes'] ?? 20 * 1024 * 1024),
            'documents.scan_mode' => (string) ($data['scan_mode'] ?? 'stub'),
            'documents.scan_provider' => (string) ($data['scan_provider'] ?? 'stub'),
            'documents.quarantine_behavior' => (string) ($data['quarantine_behavior'] ?? 'block_all'),
            'documents.rescan_policy' => (string) ($data['rescan_policy'] ?? 'manual_only'),
            'documents.rescan_max_attempts' => (int) ($data['rescan_max_attempts'] ?? 1),
            'documents.retention_days' => (int) ($data['retention_days'] ?? 365),
            'documents.retention_purge_mode' => (string) ($data['retention_purge_mode'] ?? 'archive_then_purge'),
            'documents.archive_default' => (string) ($data['archive_default'] ?? 'soft_delete'),
            'documents.download_restriction' => (string) ($data['download_restriction'] ?? 'scan_clean_only'),
            'documents.approval_requirement' => (string) ($data['approval_requirement'] ?? 'all_documents'),
            'documents.approval_required' => (bool) ($data['approval_required'] ?? true),
        ], ['scope' => 'platform', 'category' => 'documents']);
    }

    private function approvalRequirement(): string
    {
        $explicit = $this->settings->getString(
            'documents.approval_requirement',
            '',
            ['scope' => 'platform', 'category' => 'documents']
        );
        if (in_array($explicit, ['none', 'customer_visible_only', 'all_documents'], true)) {
            return $explicit;
        }

        // Backward-compatible fallback for older boolean policy.
        $legacyRequired = $this->settings->getBool('documents.approval_required', true, ['scope' => 'platform', 'category' => 'documents']);

        return $legacyRequired ? 'all_documents' : 'none';
    }
}
