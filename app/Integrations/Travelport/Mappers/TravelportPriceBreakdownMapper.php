<?php

namespace App\Integrations\Travelport\Mappers;

use App\Contracts\Integrations\Mappers\PriceBreakdownMapperInterface;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;

/**
 * Maps Travelport fare / reprice JSON to {@see PriceBreakdownData}.
 */
final class TravelportPriceBreakdownMapper implements PriceBreakdownMapperInterface
{
    public function providerCode(): string
    {
        return 'travelport';
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     */
    public function mapPriceBreakdown(array $rawSupplierPayload): PriceBreakdownData
    {
        $price = is_array($rawSupplierPayload['PriceResponse'] ?? null)
            ? $rawSupplierPayload['PriceResponse']
            : (is_array($rawSupplierPayload['BestCombinablePrice'] ?? null) ? $rawSupplierPayload['BestCombinablePrice'] : $rawSupplierPayload);

        $total = (float) ($price['TotalPrice'] ?? $price['total'] ?? 0);
        $base = (float) ($price['BasePrice'] ?? $price['base'] ?? 0);
        $tax = max(0.0, (float) ($price['Taxes'] ?? $price['tax'] ?? ($total - $base)));
        $currency = strtoupper((string) ($price['Currency'] ?? $price['currency'] ?? 'USD'));
        $offerReference = (string) ($price['OfferReference'] ?? $price['offer_reference'] ?? '');

        if ($total <= 0 && $base <= 0 && $tax <= 0) {
            return PriceBreakdownData::unavailable(
                $currency,
                $offerReference,
                NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
            );
        }

        return new PriceBreakdownData(
            currency: $currency,
            baseAmount: $base,
            taxAmount: $tax,
            feeAmount: 0.0,
            totalAmount: $total,
            status: 'confirmed',
            offerReference: $offerReference !== '' ? $offerReference : null,
            lines: [
                ['label' => 'base', 'amount' => $base],
                ['label' => 'tax', 'amount' => $tax],
            ],
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
        );
    }
}
