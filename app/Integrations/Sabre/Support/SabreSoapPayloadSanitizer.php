<?php

namespace App\Integrations\Sabre\Support;

final class SabreSoapPayloadSanitizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitizeArray(array $payload): array
    {
        $masked = [];
        foreach ($payload as $key => $value) {
            $k = strtolower((string) $key);
            if ($this->isSensitiveKey($k)) {
                $masked[$key] = '***';
                continue;
            }
            if (is_array($value)) {
                $masked[$key] = $this->sanitizeArray($value);
                continue;
            }
            $masked[$key] = $value;
        }

        return $masked;
    }

    public function sanitizeXml(string $xml): string
    {
        $patterns = [
            '/<(?:password|token|authorization|clientsecret|apikey)[^>]*>.*?<\/(?:password|token|authorization|clientsecret|apikey)>/is',
            '/(Authorization\s*:\s*Bearer\s+)[^\s<"]+/i',
        ];

        return preg_replace($patterns, ['<masked>***</masked>', '$1***'], $xml) ?? $xml;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (['password', 'token', 'authorization', 'secret', 'api_key'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}

