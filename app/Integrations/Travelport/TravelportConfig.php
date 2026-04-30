<?php

namespace App\Integrations\Travelport;

/**
 * Typed access to config/travelport.php.
 */
final readonly class TravelportConfig
{
    public function __construct(
        public ?string $baseUrl,
        public string $tokenPath,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('travelport.base_url'),
            tokenPath: (string) config('travelport.token_path', '/oauth/token'),
        );
    }
}
