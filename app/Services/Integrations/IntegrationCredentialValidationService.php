<?php

namespace App\Services\Integrations;

use App\Models\IntegrationConnection;

final class IntegrationCredentialValidationService
{
    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    public function sanitizeForPersistence(array $credentials): array
    {
        $sanitized = [];
        foreach ($credentials as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $normalized = $this->normalizeSubmittedCredentialValue($value);
            if ($normalized === null) {
                continue;
            }

            $sanitized[$key] = $normalized;
        }

        return $sanitized;
    }

    /**
     * @param  array<string, string>  $submittedCredentials
     * @return array<string, string>
     */
    public function mergeWithStoredCredentials(?IntegrationConnection $connection, array $submittedCredentials): array
    {
        if ($connection === null) {
            return $submittedCredentials;
        }

        $connection->loadMissing('credentials');
        $existing = [];
        foreach ($connection->credentials as $credential) {
            $key = trim((string) ($credential->credential_key ?? ''));
            $value = trim((string) ($credential->credential_value_encrypted ?? ''));
            if ($key === '' || $value === '') {
                continue;
            }
            $existing[$key] = $value;
        }

        return array_merge($existing, $submittedCredentials);
    }

    public function hasNonEmptyCredential(array $credentials, string $key): bool
    {
        $value = trim((string) ($credentials[$key] ?? ''));

        return $value !== '';
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeSubmittedCredentialValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $this->isMaskedPlaceholder($trimmed)) {
            return null;
        }

        return $trimmed;
    }

    private function isMaskedPlaceholder(string $value): bool
    {
        $lower = strtolower($value);

        if (str_contains($value, '•')) {
            return true;
        }
        if ((bool) preg_match('/^\*{4,}$/', $value)) {
            return true;
        }

        return str_contains($lower, 'stored')
            || str_contains($lower, 'replace')
            || str_contains($lower, 'stored securely');
    }
}
