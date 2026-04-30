<?php

namespace App\Integrations\Amadeus;

final readonly class AmadeusConfig
{
    public function __construct(
        public ?string $baseUrl,
        public string $tokenPath,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('amadeus.base_url'),
            tokenPath: (string) config('amadeus.token_path', '/v1/security/oauth2/token'),
        );
    }
}
