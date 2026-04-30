<?php

namespace App\Data\Integrations;

use JsonSerializable;

/**
 * Fare / tax totals after normalization (no raw supplier JSON).
 */
final readonly class PriceBreakdownData implements JsonSerializable
{
    /**
     * @param  list<array{label: string, amount: float}>  $lines
     */
    public function __construct(
        public string $currency,
        public float $baseAmount,
        public float $taxAmount,
        public float $feeAmount,
        public float $totalAmount,
        public string $status,
        public ?string $offerReference = null,
        public array $lines = [],
        public ?NormalizedPayloadMetadata $metadata = null,
    ) {
    }

    public static function unavailable(string $currency, string $offerReference, ?NormalizedPayloadMetadata $metadata = null): self
    {
        return new self(
            currency: $currency,
            baseAmount: 0.0,
            taxAmount: 0.0,
            feeAmount: 0.0,
            totalAmount: 0.0,
            status: 'unavailable',
            offerReference: $offerReference,
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $base = [
            'currency' => $this->currency,
            'base_amount' => $this->baseAmount,
            'tax_amount' => $this->taxAmount,
            'fee_amount' => $this->feeAmount,
            'total_amount' => $this->totalAmount,
            'status' => $this->status,
            'offer_reference' => $this->offerReference,
            'lines' => $this->lines,
        ];

        if ($this->metadata !== null) {
            return array_merge($this->metadata->jsonSerialize(), $base);
        }

        return $base;
    }
}
