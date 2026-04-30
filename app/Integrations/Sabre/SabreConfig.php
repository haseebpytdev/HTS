<?php

namespace App\Integrations\Sabre;

final readonly class SabreConfig
{
    public function __construct(
        public ?string $baseUrl,
        public string $tokenPath,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('sabre.base_url'),
            tokenPath: (string) config('sabre.token_path', '/v2/auth/token'),
        );
    }
}
