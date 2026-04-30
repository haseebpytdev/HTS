<?php

namespace App\Services\Booking;

use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\TravelerData;
use App\Integrations\Duffel\DuffelCancellationAdapter;
use App\Integrations\Duffel\DuffelPaymentAdapter;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Models\Booking;
use App\Models\Traveler;
use App\Services\Integrations\BookingRevalidationGuard;
use App\Services\Integrations\BookingOrchestrator;
use App\Services\Integrations\FlightPricingOrchestrator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hook points from the booking engine into the GDS integration layer (Phase 11).
 * Replace placeholders with real orchestration (BookingOrchestrator + provider adapters) per product rules.
 */
class BookingSupplierIntegrationBridge
{
    public const HOOK_PLACEHOLDER = 'placeholder';

    public const HOOK_QUEUED = 'queued';

    public const HOOK_CONFIRMED = 'confirmed';

    public const HOOK_FAILED = 'failed';

    private const SUPPLIER_PAYMENT_LOCAL_RECORD_SEPARATE = 'not_recorded_supplier_side_only';

    public function __construct(
        private readonly BookingOrchestrator $bookingOrchestrator,
        private readonly FlightPricingOrchestrator $flightPricingOrchestrator,
        private readonly BookingRevalidationGuard $bookingRevalidationGuard,
        private readonly DuffelPaymentAdapter $duffelPaymentAdapter,
        private readonly DuffelCancellationAdapter $duffelCancellationAdapter,
    ) {
    }

    /**
     * Called after an internal booking is confirmed — reserve flight via supplier pipeline.
     */
    public function dispatchFlightBookingPlaceholder(Booking $booking): void
    {
        if ($booking->supplier_flight_hook_status === self::HOOK_CONFIRMED) {
            return;
        }

        $context = $this->flightBookingContext($booking);
        if (! $context['has_supplier_request']) {
            $booking->forceFill([
                'supplier_flight_hook_status' => self::HOOK_QUEUED,
            ])->save();

            return;
        }

        if ($context['offer_reference'] === null) {
            $booking->forceFill([
                'supplier_flight_hook_status' => self::HOOK_FAILED,
                'internal_notes' => $this->encodeInternalNotes(array_merge(
                    $this->decodeInternalNotes((string) ($booking->internal_notes ?? '')),
                    ['supplier_booking_error' => 'Missing supplier offer reference for flight booking bridge.']
                )),
            ])->save();

            return;
        }
        if ($context['provider'] === null) {
            $booking->forceFill([
                'supplier_flight_hook_status' => self::HOOK_FAILED,
                'internal_notes' => $this->encodeInternalNotes(array_merge(
                    $this->decodeInternalNotes((string) ($booking->internal_notes ?? '')),
                    ['supplier_booking_error' => 'Missing supplier provider for flight booking bridge.']
                )),
            ])->save();

            return;
        }

        $booking->forceFill([
            'supplier_flight_hook_status' => self::HOOK_QUEUED,
        ])->save();

        try {
            $this->ensureFreshRevalidationForBookingContext($context);
            $created = $this->bookingOrchestrator->create(
                new BookingCreateRequestData(
                    offerReference: $context['offer_reference'],
                    travelers: $this->mapTravelersForSupplier($booking),
                    contactEmail: $booking->customer_email,
                    contactPhone: $booking->customer_phone,
                ),
                $context['correlation_id'],
                $context['provider']
            );

            $notes = $this->decodeInternalNotes((string) ($booking->internal_notes ?? ''));
            $notes['supplier_booking'] = [
                'provider' => $created->providerCode,
                'offer_reference' => $context['offer_reference'],
                'booking_reference' => $created->bookingReference,
                'pnr' => $created->pnr,
                'status' => $created->status,
                'correlation_id' => $context['correlation_id'],
                'total_price' => $created->totalPrice?->jsonSerialize(),
                'created_at' => $created->createdAt,
            ];

            if ($this->shouldAttemptHoldOrderPayment($created)) {
                $payment = $this->duffelPaymentAdapter->settleHoldOrderIfRequired(
                    (string) $created->bookingReference,
                    [
                        'correlation_id' => $context['correlation_id'],
                        'total_amount' => $created->totalPrice?->totalAmount,
                        'currency' => $created->totalPrice?->currency,
                    ]
                );
                $notes['supplier_payment'] = [
                    'provider' => 'duffel',
                    'status' => (string) ($payment['status'] ?? 'unknown'),
                    'requires_payment' => (bool) ($payment['requires_payment'] ?? false),
                    'payment_reference' => $payment['payment_reference'] ?? null,
                    'repriced_total_amount' => $payment['repriced_total_amount'] ?? null,
                    'repriced_currency' => $payment['repriced_currency'] ?? null,
                    'correlation_id' => $payment['correlation_id'] ?? $context['correlation_id'],
                    // Keep supplier-side payment and local gateway records explicitly separate.
                    'local_payment_record' => self::SUPPLIER_PAYMENT_LOCAL_RECORD_SEPARATE,
                ];
            }
            $booking->forceFill([
                'supplier_flight_hook_status' => self::HOOK_CONFIRMED,
                'internal_notes' => $this->encodeInternalNotes($notes),
            ])->save();

            Log::info('booking.supplier_hook.flight.confirmed', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'provider' => $created->providerCode,
                'supplier_booking_reference' => $created->bookingReference,
                'pnr' => $created->pnr,
            ]);
        } catch (Throwable $e) {
            $notes = $this->decodeInternalNotes((string) ($booking->internal_notes ?? ''));
            $notes['supplier_booking_error'] = $e->getMessage();
            $booking->forceFill([
                'supplier_flight_hook_status' => self::HOOK_FAILED,
                'internal_notes' => $this->encodeInternalNotes($notes),
            ])->save();

            Log::error('booking.supplier_hook.flight.failed', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Called after an internal booking is confirmed — reserve hotel via supplier/static inventory.
     */
    public function dispatchHotelBookingPlaceholder(Booking $booking): void
    {
        $booking->forceFill([
            'supplier_hotel_hook_status' => self::HOOK_QUEUED,
        ])->save();

        Log::info('booking.supplier_hook.hotel.placeholder', [
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'message' => 'Wire hotel confirmation (static rates or partner API) here.',
        ]);
    }

    /**
     * Run all post-confirm supplier hooks (idempotent enough for single confirm flow).
     */
    public function runPostConfirmHooks(Booking $booking): void
    {
        $this->dispatchFlightBookingPlaceholder($booking->fresh());
        $this->dispatchHotelBookingPlaceholder($booking->fresh());
    }

    /**
     * Prepare supplier-side cancellation/change metadata for admin-reviewed cancellation.
     *
     * @return array<string, mixed>
     */
    public function dispatchFlightCancellationPreparation(Booking $booking, ?string $reason = null): array
    {
        $notes = $this->decodeInternalNotes((string) ($booking->internal_notes ?? ''));
        $supplierBooking = is_array($notes['supplier_booking'] ?? null) ? $notes['supplier_booking'] : [];
        $provider = strtolower(trim((string) ($supplierBooking['provider'] ?? '')));
        $bookingReference = trim((string) ($supplierBooking['booking_reference'] ?? ''));
        if ($provider !== 'duffel' || $bookingReference === '') {
            return ['prepared' => false, 'provider' => $provider !== '' ? $provider : null];
        }

        $correlationId = trim((string) ($supplierBooking['correlation_id'] ?? ''));
        if ($correlationId === '') {
            $correlationId = 'booking-cancel-'.$booking->id.'-'.now()->timestamp;
        }

        try {
            $cancellation = $this->duffelCancellationAdapter->prepareCancellation($bookingReference, [
                'correlation_id' => $correlationId,
                'reason' => $reason,
            ]);
            $changeGroundwork = $this->duffelCancellationAdapter->prepareOrderChangeGroundwork($bookingReference, [
                'correlation_id' => $correlationId,
            ]);
            $notes['supplier_cancellation'] = $cancellation;
            $notes['supplier_change_groundwork'] = $changeGroundwork;
            $booking->forceFill([
                'internal_notes' => $this->encodeInternalNotes($notes),
            ])->save();

            Log::info('booking.supplier_hook.flight.cancel.prepared', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'provider' => $provider,
                'supplier_booking_reference' => $bookingReference,
                'cancellation_status' => (string) ($cancellation['status'] ?? 'prepared'),
            ]);

            return [
                'prepared' => true,
                'provider' => $provider,
                'supplier_booking_reference' => $bookingReference,
                'cancellation' => $cancellation,
                'change_groundwork' => $changeGroundwork,
            ];
        } catch (Throwable $e) {
            $notes['supplier_cancellation_error'] = $e->getMessage();
            $booking->forceFill([
                'internal_notes' => $this->encodeInternalNotes($notes),
            ])->save();
            Log::error('booking.supplier_hook.flight.cancel.failed', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'provider' => $provider,
                'supplier_booking_reference' => $bookingReference,
                'error' => $e->getMessage(),
            ]);

            return [
                'prepared' => false,
                'provider' => $provider,
                'supplier_booking_reference' => $bookingReference,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{
     *  has_supplier_request: bool,
     *  provider: string|null,
     *  offer_reference: string|null,
     *  correlation_id: string,
     *  selected_passengers: list<string>
     * }
     */
    private function flightBookingContext(Booking $booking): array
    {
        $notes = $this->decodeInternalNotes((string) ($booking->internal_notes ?? ''));
        $supplierBooking = is_array($notes['supplier_booking_request'] ?? null) ? $notes['supplier_booking_request'] : [];
        $offerReference = trim((string) ($supplierBooking['offer_reference'] ?? ''));
        $provider = trim((string) ($supplierBooking['provider'] ?? ''));
        $correlationId = trim((string) ($supplierBooking['correlation_id'] ?? ''));
        $selectedPassengers = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), is_array($supplierBooking['selected_passengers'] ?? null) ? $supplierBooking['selected_passengers'] : []),
            static fn (string $value): bool => $value !== ''
        ));

        return [
            'has_supplier_request' => $supplierBooking !== [],
            'provider' => $provider !== '' ? $provider : null,
            'offer_reference' => $offerReference !== '' ? $offerReference : null,
            'correlation_id' => $correlationId !== '' ? $correlationId : ('booking-bridge-'.$booking->id.'-'.now()->timestamp),
            'selected_passengers' => $selectedPassengers,
        ];
    }

    /**
     * @return list<TravelerData>
     */
    private function mapTravelersForSupplier(Booking $booking): array
    {
        $booking->loadMissing('travelers');

        return $booking->travelers
            ->map(static function (Traveler $traveler): TravelerData {
                return new TravelerData(
                    travelerType: $traveler->traveler_type,
                    givenName: (string) $traveler->first_name,
                    familyName: (string) $traveler->last_name,
                    dateOfBirth: $traveler->date_of_birth?->toDateString(),
                    nationality: is_string($traveler->nationality) ? strtoupper($traveler->nationality) : null,
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeInternalNotes(string $value): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return ['legacy_internal_note' => $value];
    }

    /**
     * @param  array<string, mixed>  $notes
     */
    private function encodeInternalNotes(array $notes): string
    {
        return (string) json_encode($notes, JSON_UNESCAPED_SLASHES);
    }

    private function shouldAttemptHoldOrderPayment(\App\Data\Integrations\BookingData $created): bool
    {
        if ($created->providerCode !== 'duffel') {
            return false;
        }

        if (! (bool) config('duffel.hold_order_payment_enabled', false)) {
            return false;
        }

        return in_array(strtolower($created->status), ['pending', 'awaiting_payment'], true);
    }

    /**
     * @param  array{
     *  provider: string|null,
     *  offer_reference: string|null,
     *  correlation_id: string,
     *  selected_passengers: list<string>
     * }  $context
     */
    private function ensureFreshRevalidationForBookingContext(array $context): void
    {
        $provider = $context['provider'];
        $offerReference = $context['offer_reference'];
        if ($provider === null || $offerReference === null) {
            return;
        }

        try {
            $this->bookingRevalidationGuard->enforceFreshSuccessfulRevalidation($offerReference, $provider);

            return;
        } catch (SupplierIntegrationException $e) {
            if ($e->normalizedCode !== 'fresh_revalidation_required') {
                throw $e;
            }
        }

        $opaqueContext = ['provider' => $provider];
        if ($context['selected_passengers'] !== []) {
            $opaqueContext['passengers'] = $context['selected_passengers'];
        }

        $result = $this->flightPricingOrchestrator->revalidateFareWithFallback(
            offerReference: $offerReference,
            opaqueContext: $opaqueContext,
            correlationId: $context['correlation_id'],
            providerOverride: $provider,
            providers: [$provider],
            allowFallback: false
        );
        $price = $result['price'];

        $this->bookingRevalidationGuard->recordRevalidationResult(
            offerReference: $offerReference,
            driver: $provider,
            status: $price->status,
            correlationId: $context['correlation_id'],
            totalAmount: (float) $price->totalAmount,
            currency: (string) $price->currency
        );
    }
}
