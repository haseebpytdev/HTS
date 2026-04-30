<?php

namespace App\Contracts\Documents;

use App\Data\Documents\DocumentScanResultData;

interface DocumentScannerInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function scan(string $absolutePath, array $context = []): DocumentScanResultData;
}
