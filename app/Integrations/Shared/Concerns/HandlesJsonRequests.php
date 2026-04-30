<?php

namespace App\Integrations\Shared\Concerns;

use App\Integrations\Shared\Exceptions\ProviderMappingException;

trait HandlesJsonRequests
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function encodeJsonBody(string $providerCode, array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ProviderMappingException('Failed to encode JSON request body', $providerCode, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonResponse(string $providerCode, string $body): array
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new ProviderMappingException('Invalid JSON from supplier', $providerCode);
        }

        return $decoded;
    }
}
