<?php

namespace App\Contracts\Integrations;

/**
 * Implemented by every supplier-facing integration surface (search, pricing, booking, auth).
 */
interface IdentifiesIntegrationProvider
{
    /**
     * Stable code matching {@see config('integrations.driver')} keys and DB `integration_connections.provider`.
     */
    public function providerCode(): string;
}
