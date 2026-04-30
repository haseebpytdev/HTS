<?php

namespace App\Contracts\Integrations;

use App\Data\Integrations\PriceBreakdownData;

/**
 * Fare revalidation / price check before ticketing.
 */
interface FlightPricingProviderInterface extends IdentifiesIntegrationProvider
{
    /**
     * @param  array<string, mixed>  $opaqueContext  Offer/session keys from search (opaque to core domain).
     */
    public function revalidateFare(string $offerReference, array $opaqueContext = []): PriceBreakdownData;
}
