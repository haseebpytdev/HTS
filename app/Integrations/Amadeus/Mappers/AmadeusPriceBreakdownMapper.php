<?php

namespace App\Integrations\Amadeus\Mappers;

use App\Contracts\Integrations\Mappers\PriceBreakdownMapperInterface;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;

final class AmadeusPriceBreakdownMapper implements PriceBreakdownMapperInterface
{
    public function providerCode(): string
    {
        return AmadeusSelfServiceProvider::CODE;
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     */
    public function mapPriceBreakdown(array $rawSupplierPayload): PriceBreakdownData
    {
        $data = is_array($rawSupplierPayload['data'] ?? null) ? $rawSupplierPayload['data'] : $rawSupplierPayload;
        $offer = is_array($data['flightOffers'][0] ?? null) ? $data['flightOffers'][0] : $data;
        $price = is_array($offer['price'] ?? null) ? $offer['price'] : [];
        if ($price === []) {
            return PriceBreakdownData::unavailable(
                'USD',
                '',
                NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
            );
        }

        $total = (float) ($price['grandTotal'] ?? 0);
        $base = (float) ($price['base'] ?? 0);
        $tax = max(0.0, $total - $base);

        return new PriceBreakdownData(
            currency: strtoupper((string) ($price['currency'] ?? 'USD')),
            baseAmount: $base,
            taxAmount: $tax,
            feeAmount: 0.0,
            totalAmount: $total,
            status: 'confirmed',
            offerReference: (string) ($offer['id'] ?? ''),
            lines: [
                ['label' => 'base', 'amount' => $base],
                ['label' => 'tax', 'amount' => $tax],
            ],
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
        );
    }
}
