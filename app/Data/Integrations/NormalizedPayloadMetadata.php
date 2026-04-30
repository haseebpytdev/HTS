<?php

namespace App\Data\Integrations;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Stamps mapper output for migrations when supplier payloads or normalized shapes change.
 *
 * @see config/integration_mapping.php
 */
final readonly class NormalizedPayloadMetadata implements JsonSerializable
{
    public function __construct(
        public string $provider,
        public string $providerApiFamily,
        public string $providerVersion,
        public string $mapperVersion,
        public string $normalizedSchemaVersion,
    ) {
    }

    /**
     * Build metadata from {@see config('integration_mapping')} for a provider + logical schema key
     * (`flight_offer`, `price_breakdown`, `booking`).
     */
    public static function forSchemaKey(string $provider, string $schemaKey): self
    {
        /** @var array<string, mixed>|null $providerConfig */
        $providerConfig = config("integration_mapping.providers.{$provider}");
        if ($providerConfig === null) {
            /** @var array<string, mixed> $providerConfig */
            $providerConfig = config('integration_mapping.providers.stub');
        }

        $schemaVersion = config("integration_mapping.normalized_schemas.{$schemaKey}");
        if (! is_string($schemaVersion) || $schemaVersion === '') {
            throw new InvalidArgumentException("Unknown normalized schema key: {$schemaKey}");
        }

        return new self(
            provider: $provider,
            providerApiFamily: (string) $providerConfig['provider_api_family'],
            providerVersion: (string) $providerConfig['provider_version'],
            mapperVersion: (string) $providerConfig['mapper_version'],
            normalizedSchemaVersion: $schemaVersion,
        );
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'provider' => $this->provider,
            'provider_api_family' => $this->providerApiFamily,
            'provider_version' => $this->providerVersion,
            'mapper_version' => $this->mapperVersion,
            'normalized_schema_version' => $this->normalizedSchemaVersion,
        ];
    }
}
