<?php

namespace Tests\Unit\Documents;

use App\Contracts\Documents\DocumentScannerInterface;
use App\Data\Documents\DocumentScanResultData;
use App\Services\Documents\DocumentScanService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DocumentScanServiceTest extends TestCase
{
    public function test_disabled_mode_returns_skipped_status(): void
    {
        Config::set('documents.scanning.mode', 'disabled');
        $service = new DocumentScanService($this->fakeScannerResult('clean'));

        $result = $service->scanFile(__FILE__);

        $this->assertSame('skipped', $result->status);
        $this->assertSame('disabled', $result->engine);
    }

    public function test_stub_mode_returns_configured_status(): void
    {
        Config::set('documents.scanning.mode', 'stub');
        Config::set('documents.scanning.stub_status', 'suspicious');
        Config::set('documents.scanning.stub_message', 'Stub suspicious');
        $service = new DocumentScanService($this->fakeScannerResult('clean'));

        $result = $service->scanFile(__FILE__);

        $this->assertSame('suspicious', $result->status);
        $this->assertSame('stub', $result->engine);
        $this->assertSame('Stub suspicious', $result->message);
    }

    public function test_real_mode_uses_provider_result(): void
    {
        Config::set('documents.scanning.mode', 'real');
        Config::set('documents.scanning.provider', 'clamav');
        Config::set('documents.scanning.require_real_scanner_in_production', false);
        $service = new DocumentScanService($this->fakeScannerResult('infected', engine: 'clamav', signature: 'EICAR'));

        $result = $service->scanFile(__FILE__);

        $this->assertSame('infected', $result->status);
        $this->assertSame('clamav', $result->engine);
        $this->assertSame('EICAR', $result->signature);
    }

    public function test_production_policy_blocks_non_real_scanner_modes(): void
    {
        Config::set('app.env', 'production');
        Config::set('documents.scanning.require_real_scanner_in_production', true);
        Config::set('documents.scanning.mode', 'stub');
        Config::set('documents.scanning.provider', 'stub');
        $service = new DocumentScanService($this->fakeScannerResult('clean'));

        $result = $service->scanFile(__FILE__);

        $this->assertSame('failed', $result->status);
        $this->assertSame('policy', $result->engine);
        $this->assertStringContainsString('real scanner is required', (string) $result->message);
    }

    private function fakeScannerResult(string $status, string $engine = 'fake', ?string $signature = null): DocumentScannerInterface
    {
        return new class ($status, $engine, $signature) implements DocumentScannerInterface {
            public function __construct(
                private readonly string $status,
                private readonly string $engine,
                private readonly ?string $signature,
            ) {
            }

            public function scan(string $absolutePath, array $context = []): DocumentScanResultData
            {
                return new DocumentScanResultData(
                    status: $this->status,
                    engine: $this->engine,
                    signature: $this->signature,
                    message: 'fake-result',
                    checkedAt: CarbonImmutable::now(),
                    durationMs: 7,
                    meta: ['path' => $absolutePath],
                );
            }
        };
    }
}
