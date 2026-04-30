<?php

namespace App\Data\Documents;

use Carbon\CarbonImmutable;

final readonly class DocumentScanResultData
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public string $status,
        public string $engine,
        public ?string $signature,
        public ?string $message,
        public CarbonImmutable $checkedAt,
        public int $durationMs = 0,
        public ?array $meta = null,
    ) {
    }
}
