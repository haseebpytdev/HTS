<?php

namespace App\Integrations\Duffel\Mappers;

use App\Contracts\Integrations\Mappers\PriceBreakdownMapperInterface;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;

final class DuffelPriceBreakdownMapper implements PriceBreakdownMapperInterface
{
    public function providerCode(): string
    {
        return 'duffel';
    }

    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     */
    public function mapPriceBreakdown(array $rawSupplierPayload): PriceBreakdownData
    {
        $offer = $this->extractOffer($rawSupplierPayload);

        $currency = strtoupper((string) ($offer['total_currency'] ?? $offer['currency'] ?? 'USD'));
        $total = $this->money($offer['total_amount'] ?? $offer['total'] ?? 0);
        $base = $this->money($offer['base_amount'] ?? $offer['base'] ?? 0);
        $tax = $this->money($offer['tax_amount'] ?? max(0.0, $total - $base));
        $fee = $this->money($offer['fee_amount'] ?? 0);
        $reference = (string) ($offer['id'] ?? $offer['offer_reference'] ?? '');
        $status = $this->normalizeStatus((string) ($offer['status'] ?? ''));

        if (! $this->hasRequiredPricingFields($offer) || $status === 'unavailable') {
            return PriceBreakdownData::unavailable(
                currency: $currency,
                offerReference: $reference,
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
            );
        }

        if ($total <= 0 && $base <= 0 && $tax <= 0 && $fee <= 0) {
            return PriceBreakdownData::unavailable(
                currency: $currency,
                offerReference: $reference,
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
            );
        }

        return new PriceBreakdownData(
            currency: $currency,
            baseAmount: $base,
            taxAmount: max(0.0, $tax),
            feeAmount: max(0.0, $fee),
            totalAmount: max(0.0, $total),
            status: 'confirmed',
            offerReference: $reference !== '' ? $reference : null,
            lines: [
                ['label' => 'base', 'amount' => $base],
                ['label' => 'tax', 'amount' => max(0.0, $tax)],
                ['label' => 'fee', 'amount' => max(0.0, $fee)],
            ],
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
        );
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function hasRequiredPricingFields(array $offer): bool
    {
        $reference = trim((string) ($offer['id'] ?? $offer['offer_reference'] ?? ''));
        $total = $offer['total_amount'] ?? $offer['total'] ?? null;
        $currency = trim((string) ($offer['total_currency'] ?? $offer['currency'] ?? ''));

        return $reference !== '' && $total !== null && $currency !== '';
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function extractOffer(array $raw): array
    {
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : $raw;
        if (is_array($data) && isset($data['type']) && strtolower((string) $data['type']) === 'offer') {
            return $data;
        }

        if (is_array($raw['offer'] ?? null)) {
            return $raw['offer'];
        }

        if (is_array($raw['offers'] ?? null) && isset($raw['offers'][0]) && is_array($raw['offers'][0])) {
            return $raw['offers'][0];
        }

        if (is_array($data['offers'] ?? null) && isset($data['offers'][0]) && is_array($data['offers'][0])) {
            return $data['offers'][0];
        }

        return is_array($data) ? $data : [];
    }

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', trim($value));
        }

        return (float) $value;
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            return 'confirmed';
        }

        if (in_array($normalized, ['cancelled', 'expired', 'invalid', 'unavailable'], true)) {
            return 'unavailable';
        }

        return 'confirmed';
    }
}

