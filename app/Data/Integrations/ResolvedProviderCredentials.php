<?php

namespace App\Data\Integrations;

/**
 * OAuth / API credentials resolved from file config and optionally {@see \App\Models\IntegrationConnection}.
 */
final readonly class ResolvedProviderCredentials
{
    public function __construct(
        public string $clientId,
        public string $clientSecret,
        public ?string $baseUrl = null,
        public ?string $tokenPath = null,
        public ?int $integrationConnectionId = null,
        public string $credentialSource = 'config',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '';
    }
}
