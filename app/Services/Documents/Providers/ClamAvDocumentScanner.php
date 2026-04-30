<?php

namespace App\Services\Documents\Providers;

use App\Contracts\Documents\DocumentScannerInterface;
use App\Data\Documents\DocumentScanResultData;
use Carbon\CarbonImmutable;

final class ClamAvDocumentScanner implements DocumentScannerInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function scan(string $absolutePath, array $context = []): DocumentScanResultData
    {
        $started = microtime(true);
        $checkedAt = CarbonImmutable::now();
        $engine = 'clamav';

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return new DocumentScanResultData(
                status: 'failed',
                engine: $engine,
                signature: null,
                message: 'File not found or unreadable for scanner.',
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
            );
        }

        $host = (string) config('documents.scanning.clamav.host', '127.0.0.1');
        $port = (int) config('documents.scanning.clamav.port', 3310);
        $timeout = (int) config('documents.scanning.clamav.timeout_seconds', 5);
        $maxBytes = (int) config('documents.scanning.max_file_size_bytes', 20 * 1024 * 1024);
        $size = filesize($absolutePath);

        if (! is_int($size) || $size <= 0 || $size > $maxBytes) {
            return new DocumentScanResultData(
                status: 'suspicious',
                engine: $engine,
                signature: null,
                message: 'File size outside scanner policy limits.',
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
                meta: ['size_bytes' => $size],
            );
        }

        $socket = @fsockopen($host, (string) $port, $errno, $errstr, $timeout);
        if (! is_resource($socket)) {
            return new DocumentScanResultData(
                status: 'failed',
                engine: $engine,
                signature: null,
                message: trim("ClamAV connection failed: {$errno} {$errstr}"),
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
                meta: ['host' => $host, 'port' => $port],
            );
        }

        stream_set_timeout($socket, $timeout);
        fwrite($socket, "zINSTREAM\0");

        $fh = fopen($absolutePath, 'rb');
        if (! is_resource($fh)) {
            fclose($socket);

            return new DocumentScanResultData(
                status: 'failed',
                engine: $engine,
                signature: null,
                message: 'Unable to open file stream for scanner.',
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
            );
        }

        while (! feof($fh)) {
            $chunk = fread($fh, 8192);
            if ($chunk === false) {
                fclose($fh);
                fclose($socket);

                return new DocumentScanResultData(
                    status: 'failed',
                    engine: $engine,
                    signature: null,
                    message: 'Scanner stream read failed.',
                    checkedAt: $checkedAt,
                    durationMs: $this->durationMs($started),
                );
            }
            $len = strlen($chunk);
            if ($len > 0) {
                fwrite($socket, pack('N', $len));
                fwrite($socket, $chunk);
            }
        }
        fclose($fh);
        fwrite($socket, pack('N', 0));

        $response = trim((string) fgets($socket));
        fclose($socket);

        if ($response === '') {
            return new DocumentScanResultData(
                status: 'failed',
                engine: $engine,
                signature: null,
                message: 'Empty response from ClamAV.',
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
            );
        }

        if (str_contains($response, 'FOUND')) {
            $signature = null;
            if (preg_match('/:\s(.+)\sFOUND$/', $response, $matches) === 1) {
                $signature = $matches[1];
            }

            return new DocumentScanResultData(
                status: 'infected',
                engine: $engine,
                signature: $signature,
                message: 'Scanner reported malicious signature.',
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
                meta: ['raw_response' => $response],
            );
        }

        if (str_contains($response, 'OK')) {
            return new DocumentScanResultData(
                status: 'clean',
                engine: $engine,
                signature: null,
                message: 'No threats found.',
                checkedAt: $checkedAt,
                durationMs: $this->durationMs($started),
                meta: ['raw_response' => $response],
            );
        }

        return new DocumentScanResultData(
            status: 'suspicious',
            engine: $engine,
            signature: null,
            message: 'Unexpected scanner response.',
            checkedAt: $checkedAt,
            durationMs: $this->durationMs($started),
            meta: ['raw_response' => $response],
        );
    }

    private function durationMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
