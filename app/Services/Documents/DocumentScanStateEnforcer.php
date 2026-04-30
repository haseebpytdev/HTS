<?php

namespace App\Services\Documents;

use App\Models\BookingDocument;

final class DocumentScanStateEnforcer
{
    public function __construct(
        private readonly DocumentSecuritySettingsService $settings,
    ) {
    }

    public function canDownload(BookingDocument $document, string $actorType = 'admin'): bool
    {
        $policy = $this->settings->policy();
        $quarantineBehavior = (string) ($policy['quarantine_behavior'] ?? 'block_all');
        $downloadRestriction = (string) ($policy['download_restriction'] ?? 'scan_clean_only');
        $actor = strtolower($actorType) === 'customer' ? 'customer' : 'admin';

        if ($quarantineBehavior === 'allow_all') {
            return true;
        }

        if ($document->isQuarantined()) {
            if ($quarantineBehavior === 'admin_review_only' && $actor === 'admin') {
                return true;
            }

            return false;
        }

        if (! $document->isScanSafe()) {
            return false;
        }

        if ($downloadRestriction === 'validated_and_clean') {
            return $document->validation_status === 'approved';
        }
        if ($downloadRestriction === 'admin_only_until_approved' && $actor === 'customer') {
            return $document->validation_status === 'approved';
        }

        return true;
    }

    public function canApprove(BookingDocument $document): bool
    {
        $policy = $this->settings->policy();
        $requirement = (string) ($policy['approval_requirement'] ?? 'all_documents');
        $legacyRequired = (bool) ($policy['approval_required'] ?? true);
        if ($requirement === 'none' || (! $legacyRequired && $requirement !== 'all_documents')) {
            return true;
        }
        if ($requirement === 'customer_visible_only' && ! (bool) $document->is_customer_visible) {
            return true;
        }

        return $document->isScanSafe();
    }

    public function blockedReason(BookingDocument $document): string
    {
        return match ($document->virus_scan_status) {
            BookingDocument::SCAN_PENDING, null => 'Document scan is pending. Please wait for scan completion.',
            BookingDocument::SCAN_INFECTED => 'Document is blocked because malicious content was detected.',
            BookingDocument::SCAN_SUSPICIOUS => 'Document is blocked because suspicious content was detected.',
            BookingDocument::SCAN_FAILED => 'Document is blocked because scan failed and requires re-scan.',
            BookingDocument::SCAN_SKIPPED => 'Document is blocked because it was not scanned.',
            default => 'Document is blocked due to scan policy.',
        };
    }
}
