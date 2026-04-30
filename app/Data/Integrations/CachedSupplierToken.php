<?php

namespace App\Data\Integrations;

use Carbon\CarbonImmutable;

/**
 * In-memory representation of a cached OAuth / session token (before JSON/cache persistence).
 */
final readonly class CachedSupplierToken
{
    public function __construct(
        public string $accessToken,
        public CarbonImmutable $expiresAt,
        public string $tokenType = 'Bearer',
        public ?string $refreshToken = null,
    ) {
    }

    /**
     * @param  array{access_token: string, expires_at: string, token_type?: string, refresh_token?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            expiresAt: CarbonImmutable::parse($data['expires_at']),
            tokenType: $data['token_type'] ?? 'Bearer',
            refreshToken: $data['refresh_token'] ?? null,
        );
    }

    /**
     * @return array{access_token: string, expires_at: string, token_type: string, refresh_token: string|null}
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'token_type' => $this->tokenType,
            'refresh_token' => $this->refreshToken,
        ];
    }
}
