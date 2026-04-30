<?php

namespace App\Integrations\Stub;

use App\Contracts\Integrations\BookingProviderInterface;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;
use App\Data\Integrations\TravelerData;

final class StubBookingAdapter implements BookingProviderInterface
{
    public function __construct(
        private readonly string $provider = 'stub',
    ) {
    }

    public function providerCode(): string
    {
        return $this->provider;
    }

    public function createBooking(BookingCreateRequestData $request): BookingData
    {
        /** @var array<string, string> $perProvider */
        $perProvider = config('integrations.stub_provider_scenarios', []);
        $scenario = (string) ($perProvider[$this->providerCode()] ?? config('integrations.stub_scenario', 'booking_success'));
        if ($scenario !== 'booking_success') {
            $failureFixture = StubFixtureLoader::load($this->providerCode(), $scenario);
            throw StubFailureFactory::fromFixture($this->providerCode(), $failureFixture);
        }

        $fixture = StubFixtureLoader::load($this->providerCode(), 'booking_success');
        $booking = $this->extractBooking($fixture);
        $price = $booking['total_price'] ?? $booking['price'] ?? [];
        $travelers = [];
        foreach (($booking['travelers'] ?? $booking['passengers'] ?? []) as $traveler) {
            $travelers[] = new TravelerData(
                travelerType: (string) ($traveler['traveler_type'] ?? 'adult'),
                givenName: (string) ($traveler['given_name'] ?? $traveler['firstName'] ?? 'Unknown'),
                familyName: (string) ($traveler['family_name'] ?? $traveler['lastName'] ?? 'Traveler'),
                dateOfBirth: isset($traveler['date_of_birth']) ? (string) $traveler['date_of_birth'] : null,
                nationality: isset($traveler['nationality']) ? (string) $traveler['nationality'] : null,
            );
        }

        return new BookingData(
            status: (string) ($booking['status'] ?? 'confirmed'),
            providerCode: $this->providerCode(),
            bookingReference: isset($booking['booking_reference']) ? (string) $booking['booking_reference'] : ((isset($booking['id']) ? (string) $booking['id'] : null)),
            pnr: isset($booking['pnr']) ? (string) $booking['pnr'] : ((isset($booking['recordLocator']) ? (string) $booking['recordLocator'] : null)),
            travelers: $travelers,
            totalPrice: new PriceBreakdownData(
                currency: (string) ($price['currency'] ?? $price['currencyCode'] ?? 'USD'),
                baseAmount: (float) ($price['base_amount'] ?? $price['base'] ?? 0),
                taxAmount: (float) ($price['tax_amount'] ?? $price['taxes'] ?? 0),
                feeAmount: (float) ($price['fee_amount'] ?? $price['fees'] ?? 0),
                totalAmount: (float) ($price['total_amount'] ?? $price['grandTotal'] ?? $price['total'] ?? 0),
                status: 'available',
                offerReference: $request->offerReference,
                metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'price_breakdown'),
            ),
            createdAt: isset($booking['created_at']) ? (string) $booking['created_at'] : ((isset($booking['createdAt']) ? (string) $booking['createdAt'] : null)),
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    public function retrieveBooking(string $bookingReference): BookingData
    {
        return new BookingData(
            status: 'not_found',
            providerCode: $this->providerCode(),
            bookingReference: $bookingReference,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    public function cancelBooking(string $bookingReference, array $opaqueContext = []): BookingData
    {
        return new BookingData(
            status: 'not_implemented',
            providerCode: $this->providerCode(),
            bookingReference: $bookingReference,
            metadata: NormalizedPayloadMetadata::forSchemaKey($this->providerCode(), 'booking'),
        );
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function extractBooking(array $fixture): array
    {
        if ($this->providerCode() === 'travelport') {
            return $fixture['BookingResponse'] ?? [];
        }
        if ($this->providerCode() === 'sabre') {
            return $fixture['CreatePassengerNameRecordRS'] ?? [];
        }
        if ($this->providerCode() === 'amadeus') {
            return $fixture['data'] ?? [];
        }

        return $fixture['booking'] ?? [];
    }
}
