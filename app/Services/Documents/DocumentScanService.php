<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentScannerInterface;
use App\Data\Documents\DocumentScanResultData;
use App\Services\Documents\Providers\ClamAvDocumentScanner;
use Carbon\CarbonImmutable;

final class DocumentScanService
{
    public function __construct(
        private readonly DocumentScannerInterface $scanner,
        private readonly DocumentSecuritySettingsService $settings,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function scanFile(string $absolutePath, array $context = []): DocumentScanResultData
    {
        $policy = $this->settings->policy();
        $mode = (string) ($policy['scan_mode'] ?? config('documents.scanning.mode', 'disabled'));
        $provider = (string) ($policy['scan_provider'] ?? config('documents.scanning.provider', 'stub'));
        $maxScanSizeBytes = (int) ($policy['max_scan_file_size_bytes'] ?? config('documents.scanning.max_file_size_bytes', 20 * 1024 * 1024));
        $checkedAt = CarbonImmutable::now();
        $started = microtime(true);
        $appEnv = strtolower((string) config('app.env', 'production'));
        $requireRealProd = (bool) config('documents.scanning.require_real_scanner_in_production', true);

        if ($requireRealProd && $appEnv === 'production' && ($mode !== 'real' || $provider !== 'clamav')) {
            return new DocumentScanResultData(
                status: 'failed',
                engine: 'policy',
                signature: null,
                message: 'Production scanner policy violation: real scanner is required.',
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
            );
        }

        if ($mode === 'disabled') {
            return new DocumentScanResultData(
                status: 'skipped',
                engine: 'disabled',
                signature: null,
                message: 'Scanning disabled by configuration.',
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
            );
        }

        if ($mode === 'stub') {
            $status = (string) config('documents.scanning.stub_status', 'clean');
            $status = $this->normalizeStatus($status);

            return new DocumentScanResultData(
                status: $status,
                engine: 'stub',
                signature: $status === 'infected' ? 'EICAR-Test-Signature' : null,
                message: (string) config('documents.scanning.stub_message', 'Stub scan result'),
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
                meta: [
                    'stub_status' => $status,
                ],
            );
        }

        $sizeBytes = @filesize($absolutePath);
        if (is_int($sizeBytes) && $sizeBytes > $maxScanSizeBytes) {
            return new DocumentScanResultData(
                status: 'failed',
                engine: 'policy',
                signature: null,
                message: 'Scanner skipped due to file size policy limit.',
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
                meta: [
                    'policy' => 'max_scan_file_size_bytes',
                    'size_bytes' => $sizeBytes,
                    'max_scan_file_size_bytes' => $maxScanSizeBytes,
                ],
            );
        }

        $result = $this->scanner->scan($absolutePath, $context);
        $status = $this->normalizeStatus($result->status);

        return new DocumentScanResultData(
            status: $status,
            engine: $result->engine,
            signature: $result->signature,
            message: $result->message,
            checkedAt: $result->checkedAt,
            durationMs: $result->durationMs,
            meta: $result->meta,
        );
    }

    public static function buildConfiguredScanner(): DocumentScannerInterface
    {
        $provider = (string) config('documents.scanning.provider', 'clamav');

        return match ($provider) {
            'clamav' => new ClamAvDocumentScanner(),
            default => new ClamAvDocumentScanner(),
        };
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        $allowed = ['clean', 'infected', 'suspicious', 'failed', 'skipped'];

        return in_array($normalized, $allowed, true) ? $normalized : 'failed';
    }

    private function durationMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
