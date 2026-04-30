<?php

namespace App\Integrations\Stub;

use App\Contracts\Integrations\FlightPricingProviderInterface;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;

final class StubFlightPriceAdapter implements FlightPricingProviderInterface
{
    public function __construct(
        private readonly string $provider = 'stub',
    ) {
    }

    public function providerCode(): string
    {
        return $this->provider;
    }

    public function revalidateFare(string $offerReference, array $opaqueContext = []): PriceBreakdownData
    {
        /** @var array<string, string> $perProvider */
        $perProvider = config('integrations.stub_provider_scenarios', []);
        $scenario = (string) ($opaqueContext['scenario'] ?? $perProvider[$this->providerCode()] ?? config('integrations.stub_scenario', 'flight_pricing_success'));
        if ($scenario !== 'flight_pricing_success') {
            $failureFixture = StubFixtureLoader::load($this->providerCode(), $scenario);
            throw StubFailureFactory::fromFixture($this->providerCode(), $failureFixture);
        }

        $fixture = StubFixtureLoader::load($this->providerCode(), 'flight_pricing_success');
        $pricing = $this->extractPricing($fixture);

        /** @var list<array{label: string, amount: float}> $lines */
        $lines = [];
        foreach (($pricing['lines'] ?? []) as $line) {
            $lines[] = [
                'label' => (string) ($line['label'] ?? 'Unknown'),
                'amount' => (float) ($line['amount'] ?? 0),
            ];
        }

        $currency = (string) ($pricing['currency'] ?? $pricing['currencyCode'] ?? 'USD');
        $baseAmount = (float) ($pricing['base_amount'] ?? $pricing['base'] ?? 0);
        $taxAmount = (float) ($pricing['tax_amount'] ?? $pricing['taxes'] ?? 0);
        $feeAmount = (float) ($pricing['fee_amount'] ?? $pricing['fees'] ?? 0);
        $totalAmount = (float) ($pricing['total_amount'] ?? $pricing['grandTotal'] ?? $pricing['total'] ?? 0);
        $status = (string) ($pricing['status'] ?? 'available');

        return new PriceBreakdownData(
            currency: $currency,
            baseAmount: $baseAmount,
            taxAmount: $taxAmount,
            feeAmount: $feeAmount,
            totalAmount: $totalAmount,
            status: $status,
            offerReference: $offerReference,
            lines: $lines,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown')
        );
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function extractPricing(array $fixture): array
    {
        if ($this->providerCode() === 'travelport') {
            return $fixture['CatalogProductOfferingsResponse']['Price'] ?? [];
        }
        if ($this->providerCode() === 'sabre') {
            return $fixture['priceQuote']['totals'] ?? [];
        }
        if ($this->providerCode() === 'amadeus') {
            return $fixture['data'][0]['price'] ?? [];
        }

        return $fixture['pricing'] ?? [];
    }
}
