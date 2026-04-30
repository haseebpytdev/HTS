<?php

namespace App\Integrations\Shared;

use App\Contracts\Integrations\IdentifiesIntegrationProvider;
use App\Data\Integrations\NormalizedPayloadMetadata;

/**
 * Optional base for provider adapters (search / price / booking).
 */
abstract class BaseProviderAdapter implements IdentifiesIntegrationProvider
{
    abstract public function providerCode(): string;

    /**
     * Stamp normalized payloads using {@see config('integration_mapping')}.
     *
     * @param  'flight_offer'|'price_breakdown'|'booking'  $schemaKey
     */
    protected function normalizedMetadata(string $schemaKey): NormalizedPayloadMetadata
    {
        return NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), $schemaKey);
    }
}
