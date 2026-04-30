<?php

namespace App\Repositories;

use App\Data\Integrations\CachedSupplierToken;
use App\Models\IntegrationConnection;
use App\Models\IntegrationToken;
use Carbon\CarbonImmutable;

final class IntegrationTokenRepository
{
    public function findLatestValidAccessToken(IntegrationConnection $connection): ?CachedSupplierToken
    {
        $row = IntegrationToken::query()
            ->where('integration_connection_id', $connection->id)
            ->where('kind', 'access')
            ->orderByDesc('id')
            ->first();

        if ($row === null || $row->expires_at === null) {
            return null;
        }

        $expiresAt = CarbonImmutable::parse($row->expires_at);
        if ($expiresAt->isPast()) {
            return null;
        }

        return new CachedSupplierToken(
            accessToken: $row->token,
            expiresAt: $expiresAt,
            tokenType: is_array($row->meta) && isset($row->meta['token_type']) ? (string) $row->meta['token_type'] : 'Bearer',
            refreshToken: $row->refresh_token,
        );
    }

    public function storeAccessToken(IntegrationConnection $connection, CachedSupplierToken $token): void
    {
        $this->deleteAccessTokens($connection);

        IntegrationToken::create([
            'integration_connection_id' => $connection->id,
            'kind' => 'access',
            'token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'expires_at' => $token->expiresAt->toDateTimeString(),
            'scope' => null,
            'meta' => array_filter([
                'token_type' => $token->tokenType,
            ]),
        ]);
    }

    public function deleteAccessTokens(IntegrationConnection $connection): void
    {
        IntegrationToken::query()
            ->where('integration_connection_id', $connection->id)
            ->where('kind', 'access')
            ->delete();
    }
}
