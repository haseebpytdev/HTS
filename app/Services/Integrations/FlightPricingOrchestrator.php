<?php

namespace App\Services\Integrations;

use App\Data\Integrations\PriceBreakdownData;
use Illuminate\Support\Str;
use Throwable;

final class FlightPricingOrchestrator
{
    public function __construct(
        private readonly IntegrationOrchestrationService $orchestration,
        private readonly IntegrationFlowRecorder $recorder,
    ) {
    }

    public function revalidateFare(string $offerReference, array $opaqueContext = [], ?string $correlationId = null, ?string $providerOverride = null): PriceBreakdownData
    {
        $cid = $correlationId ?? Str::uuid()->toString();
        $driver = $this->orchestration->resolveDriver($providerOverride, null, 'pricing');
        $context = $this->buildOpaqueContext($opaqueContext, $driver, $offerReference, $cid);
        $effectiveOfferReference = $this->resolveOfferReference($offerReference, $context);

        $this->recorder->beginFlightPricing($cid, $driver, $effectiveOfferReference);

        try {
            $price = $this->orchestration->flightPricing($driver)->revalidateFare($effectiveOfferReference, $context);
            $this->recorder->completeFlightPricing($cid, $driver, $price);

            return $price;
        } catch (Throwable $e) {
            $this->recorder->failFlightPricing($cid, $driver, $effectiveOfferReference, $e);
            throw $e;
        }
    }

    /**
     * Revalidate with fallback order. First successful provider wins.
     *
     * @param  list<string>|null  $providers
     * @return array{
     *   correlation_id: string,
     *   driver: string,
     *   failed_providers: list<array{provider: string, error: string}>,
     *   price: PriceBreakdownData
     * }
     */
    public function revalidateFareWithFallback(
        string $offerReference,
        array $opaqueContext = [],
        ?string $correlationId = null,
        ?string $providerOverride = null,
        ?array $providers = null,
        bool $allowFallback = true
    ): array {
        $cid = $correlationId ?? Str::uuid()->toString();
        $providerFromOpaque = isset($opaqueContext['provider']) ? (string) $opaqueContext['provider'] : null;
        $ordered = $this->orchestration->resolveProviderOrder($providerOverride ?? $providerFromOpaque, $providers, 'pricing');
        $failed = [];
        $firstError = null;

        foreach ($ordered as $index => $driver) {
            $context = $this->buildOpaqueContext($opaqueContext, $driver, $offerReference, $cid);
            $effectiveOfferReference = $this->resolveOfferReference($offerReference, $context);
            $this->recorder->beginFlightPricing($cid, $driver, $effectiveOfferReference);
            try {
                $price = $this->orchestration->flightPricing($driver)->revalidateFare($effectiveOfferReference, $context);
                $this->recorder->completeFlightPricing($cid, $driver, $price);

                return [
                    'correlation_id' => $cid,
                    'driver' => $driver,
                    'failed_providers' => $failed,
                    'price' => $price,
                ];
            } catch (Throwable $e) {
                $this->recorder->failFlightPricing($cid, $driver, $effectiveOfferReference, $e);
                $failed[] = ['provider' => $driver, 'error' => $e->getMessage()];
                $firstError ??= $e;
                if (! $allowFallback && $index === 0) {
                    throw $e;
                }
            }
        }

        throw $firstError ?? new \RuntimeException('Fare revalidation failed for all providers.');
    }

    /**
     * @param  array<string, mixed>  $opaqueContext
     * @return array<string, mixed>
     */
    private function buildOpaqueContext(array $opaqueContext, string $driver, string $offerReference, string $correlationId): array
    {
        $context = array_merge(['correlation_id' => $correlationId], $opaqueContext);
        $context['provider'] = $driver;
        $context['requested_offer_reference'] = $offerReference;

        $selectedOffer = is_array($context['selected_offer'] ?? null) ? $context['selected_offer'] : null;
        if ($selectedOffer !== null && ! isset($context['selected_offer_reference'])) {
            foreach (['provider_offer_reference', 'offer_reference', 'id'] as $key) {
                $candidate = trim((string) ($selectedOffer[$key] ?? ''));
                if ($candidate !== '') {
                    $context['selected_offer_reference'] = $candidate;
                    break;
                }
            }
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $opaqueContext
     */
    private function resolveOfferReference(string $offerReference, array $opaqueContext): string
    {
        $fromSelected = trim((string) ($opaqueContext['selected_offer_reference'] ?? ''));

        return $fromSelected !== '' ? $fromSelected : $offerReference;
    }
}
