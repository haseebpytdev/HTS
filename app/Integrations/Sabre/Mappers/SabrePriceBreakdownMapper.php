<?php

namespace App\Integrations\Sabre\Mappers;

use App\Contracts\Integrations\Mappers\PriceBreakdownMapperInterface;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;

final class SabrePriceBreakdownMapper implements PriceBreakdownMapperInterface
{
    public function providerCode(): string
    {
        return 'sabre';
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     */
    public function mapPriceBreakdown(array $rawSupplierPayload): PriceBreakdownData
    {
        $pricing = $this->extractPricing($rawSupplierPayload);

        $total = $this->money($pricing['totalFare'] ?? $pricing['grandTotal'] ?? 0);
        $base = $this->money($pricing['baseFare'] ?? $pricing['base'] ?? 0);
        $tax = max(0.0, $this->money($pricing['taxes'] ?? $pricing['tax'] ?? ($total - $base)));
        $total = max($total, $base + $tax);
        $currency = strtoupper((string) ($pricing['currency'] ?? 'USD'));
        $offerReference = (string) ($pricing['pricingToken'] ?? $pricing['offer_reference'] ?? '');

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
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function extractPricing(array $raw): array
    {
        if (is_array($raw['OTA_AirPriceRS'] ?? null)) {
            $raw = $raw['OTA_AirPriceRS'];
        } elseif (is_array($raw['price'] ?? null)) {
            return $raw['price'];
        }

        $soapTotalFare = data_get($raw, 'PricedItineraries.PricedItinerary.AirItineraryPricingInfo.ItinTotalFare', data_get($raw, 'ItinTotalFare', []));
        if (! is_array($soapTotalFare) || $soapTotalFare === []) {
            return $raw;
        }

        return [
            'currency' => data_get($soapTotalFare, 'TotalFare.@attributes.CurrencyCode', data_get($soapTotalFare, 'BaseFare.@attributes.CurrencyCode', 'USD')),
            'baseFare' => data_get($soapTotalFare, 'BaseFare.@attributes.Amount', data_get($soapTotalFare, 'BaseFare.Amount', 0)),
            'taxes' => data_get($soapTotalFare, 'Taxes.@attributes.Amount', data_get($soapTotalFare, 'Taxes.Amount', 0)),
            'totalFare' => data_get($soapTotalFare, 'TotalFare.@attributes.Amount', data_get($soapTotalFare, 'TotalFare.Amount', 0)),
            'pricingToken' => data_get($raw, 'PricingToken', data_get($raw, 'pricedItineraryReference', '')),
        ];
    }

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return (float) $value;
    }
}
