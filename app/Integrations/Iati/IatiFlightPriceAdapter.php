<?php

namespace App\Integrations\Iati;

use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;

final class IatiFlightPriceAdapter implements FlightPricingProviderInterface
{
    public function __construct(
        private readonly IatiClient $client,
    ) {
    }

    public function providerCode(): string
    {
        return 'iati';
    }

    public function revalidateFare(string $offerReference, array $opaqueContext = []): PriceBreakdownData
    {
        $this->client->baseUrl();

        return PriceBreakdownData::unavailable(
            currency: 'USD',
            offerReference: $offerReference,
            metadata: NormalizedPayloadMetadata::forSchemaKey('iati', 'price_breakdown'),
        );
    }
}
