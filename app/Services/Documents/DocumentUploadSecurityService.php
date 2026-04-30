<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class DocumentUploadSecurityService
{
    public function __construct(
        private readonly DocumentSecuritySettingsService $settings,
    ) {
    }

    /**
     * @return array{category:string, extension:string, detected_mime:string, size_bytes:int}
     */
    public function inspect(UploadedFile $file): array
    {
        $detectedMime = strtolower((string) $file->getMimeType());
        $clientMime = strtolower((string) $file->getClientMimeType());
        $clientExtension = strtolower((string) $file->getClientOriginalExtension());
        $guessedExtension = strtolower((string) ($file->guessExtension() ?? ''));
        $sizeBytes = (int) $file->getSize();

        $blockedExtensions = array_map('strtolower', (array) config('documents.upload.blocked_extensions', []));
        $blockedMimes = array_map('strtolower', (array) config('documents.upload.blocked_mimes', []));
        if (in_array($clientExtension, $blockedExtensions, true) || in_array($detectedMime, $blockedMimes, true) || in_array($clientMime, $blockedMimes, true)) {
            return ['category' => 'blocked', 'extension' => $clientExtension, 'detected_mime' => $detectedMime, 'size_bytes' => $sizeBytes];
        }

        if ($this->looksExecutableContent($file)) {
            return ['category' => 'blocked', 'extension' => $clientExtension, 'detected_mime' => $detectedMime, 'size_bytes' => $sizeBytes];
        }

        $policy = $this->settings->policy();
        $maxBytes = (int) ($policy['max_file_size_bytes'] ?? (int) config('documents.upload.max_file_size_bytes', 10 * 1024 * 1024));
        if ($sizeBytes <= 0 || $sizeBytes > $maxBytes) {
            return ['category' => 'oversize', 'extension' => $clientExtension, 'detected_mime' => $detectedMime, 'size_bytes' => $sizeBytes];
        }

        $images = [
            'mimes' => (array) ($policy['allowed_image_mimes'] ?? []),
            'extensions' => (array) ($policy['allowed_image_extensions'] ?? []),
        ];
        $docs = [
            'mimes' => (array) ($policy['allowed_document_mimes'] ?? []),
            'extensions' => (array) ($policy['allowed_document_extensions'] ?? []),
        ];
        $category = str_starts_with($detectedMime, 'image/') ? 'images' : 'documents';
        $allow = $category === 'images' ? $images : $docs;

        $allowedMimes = array_map('strtolower', (array) ($allow['mimes'] ?? []));
        $allowedExtensions = array_map('strtolower', (array) ($allow['extensions'] ?? []));

        $mimeAllowed = in_array($detectedMime, $allowedMimes, true) && in_array($clientMime, $allowedMimes, true);
        $extensionAllowed = in_array($clientExtension, $allowedExtensions, true);
        $guessAllowed = $guessedExtension === '' || in_array($guessedExtension, $allowedExtensions, true);

        if (! $mimeAllowed || ! $extensionAllowed || ! $guessAllowed) {
            return ['category' => 'unsupported', 'extension' => $clientExtension, 'detected_mime' => $detectedMime, 'size_bytes' => $sizeBytes];
        }

        return [
            'category' => $category,
            'extension' => $guessedExtension !== '' ? $guessedExtension : $clientExtension,
            'detected_mime' => $detectedMime,
            'size_bytes' => $sizeBytes,
        ];
    }

    public function safeStorageDirectory(int $bookingId): string
    {
        return 'booking-documents/'.$bookingId.'/uploads';
    }

    public function safeStorageFileName(string $extension): string
    {
        return now()->format('YmdHis').'_'.$this->safeRandomToken().'.'.$extension;
    }

    private function safeRandomToken(): string
    {
        return Str::lower(Str::random(16));
    }

    private function looksExecutableContent(UploadedFile $file): bool
    {
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return false;
        }

        $fh = @fopen($path, 'rb');
        if (! is_resource($fh)) {
            return false;
        }
        $head = (string) fread($fh, 16);
        fclose($fh);

        if ($head === '') {
            return false;
        }

        // "MZ" executable header or shebang scripts are blocked.
        if (str_starts_with($head, "MZ") || str_starts_with($head, '#!')) {
            return true;
        }

        return false;
    }
}
