<?php

namespace App\Integrations\Duffel;

use App\Data\Integrations\CachedSupplierToken;
use App\Integrations\Shared\AbstractSupplierAuthService;
use App\Integrations\Shared\SupplierTokenCache;
use App\Repositories\IntegrationConnectionRepository;
use App\Repositories\IntegrationTokenRepository;
use App\Services\Integrations\ProviderCredentialResolver;
use Carbon\CarbonImmutable;

final class DuffelAuthService extends AbstractSupplierAuthService
{
    public function __construct(
        SupplierTokenCache $tokenCache,
        IntegrationConnectionRepository $connectionRepository,
        IntegrationTokenRepository $tokenRepository,
        ProviderCredentialResolver $credentialResolver,
    ) {
        parent::__construct($tokenCache, $connectionRepository, $tokenRepository, $credentialResolver);
    }

    public function providerCode(): string
    {
        return 'duffel';
    }

    protected function fetchNewToken(): CachedSupplierToken
    {
        $selection = $this->credentialResolver->resolveDuffelTokenSelection(operation: 'search');
        $token = trim($selection['token']);

        if ($token === '') {
            $token = 'duffel-unconfigured';
        }

        return new CachedSupplierToken(
            accessToken: $token,
            expiresAt: CarbonImmutable::now()->addHours(24),
            tokenType: 'Bearer',
        );
    }
}
