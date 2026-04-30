<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\BookingAmendmentProviderInterface;
use App\Contracts\Integrations\BookingTicketingProviderInterface;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\BookingData;
use Illuminate\Support\Str;
use Throwable;

final class BookingOrchestrator
{
    private const STAGE_REVALIDATION_PASSED = 'revalidation_passed';
    private const STAGE_PNR_CREATED = 'pnr_created';
    private const STAGE_TICKETING_PENDING = 'ticketing_pending';
    private const STAGE_TICKETED = 'ticketed';
    private const STAGE_CANCELLED = 'cancelled';
    private const STAGE_AMENDED = 'amended';

    public function __construct(
        private readonly IntegrationOrchestrationService $orchestration,
        private readonly IntegrationFlowRecorder $recorder,
        private readonly BookingRevalidationGuard $revalidationGuard,
    ) {
    }

    public function create(BookingCreateRequestData $request, ?string $correlationId = null, ?string $providerOverride = null): BookingData
    {
        $cid = $correlationId ?? Str::uuid()->toString();
        $driver = $this->orchestration->resolveDriver($providerOverride, null, 'booking');
        $this->revalidationGuard->enforceFreshSuccessfulRevalidation($request->offerReference, $driver);
        $revalidationSnapshot = $this->revalidationGuard->snapshot($request->offerReference, $driver);

        $this->recorder->beginBookingCreate($cid, $driver);
        $this->recorder->recordBookingLifecycleStage($cid, $driver, self::STAGE_REVALIDATION_PASSED, [
            'offer_reference' => $request->offerReference,
            'revalidation_snapshot' => $revalidationSnapshot,
        ]);

        try {
            $booking = $this->orchestration->booking($driver)->createBooking($request);
            $this->recorder->recordBookingLifecycleStage($cid, $driver, self::STAGE_PNR_CREATED, [
                'offer_reference' => $request->offerReference,
                'booking_reference' => $booking->bookingReference,
                'pnr' => $booking->pnr,
            ]);
            $isTicketed = in_array(strtolower($booking->status), ['confirmed', 'ticketed'], true);
            $this->recorder->recordBookingLifecycleStage($cid, $driver, $isTicketed ? self::STAGE_TICKETED : self::STAGE_TICKETING_PENDING, [
                'booking_reference' => $booking->bookingReference,
                'status' => $booking->status,
            ]);
            $this->recorder->completeBookingCreate($cid, $driver, $booking);

            return $booking;
        } catch (Throwable $e) {
            $this->recorder->failBookingCreate($cid, $driver, $e);
            throw $e;
        }
    }

    public function retrieve(string $bookingReference, ?string $correlationId = null, ?string $providerOverride = null): BookingData
    {
        $cid = $correlationId ?? Str::uuid()->toString();
        $driver = $this->orchestration->resolveDriver($providerOverride, null, 'booking');
        $booking = $this->orchestration->booking($driver)->retrieveBooking($bookingReference);
        $this->recorder->recordBookingLifecycleStage($cid, $driver, 'retrieved', [
            'booking_reference' => $bookingReference,
            'status' => $booking->status,
        ]);

        return $booking;
    }

    public function ticket(string $bookingReference, ?string $correlationId = null, ?string $providerOverride = null): BookingData
    {
        $cid = $correlationId ?? Str::uuid()->toString();
        $driver = $this->orchestration->resolveDriver($providerOverride, null, 'booking');
        $provider = $this->orchestration->booking($driver);
        $booking = $provider instanceof BookingTicketingProviderInterface
            ? $provider->ticketBooking($bookingReference, ['correlation_id' => $cid])
            : $provider->retrieveBooking($bookingReference);
        $normalized = strtolower($booking->status);
        if (! in_array($normalized, ['confirmed', 'ticketed'], true)) {
            throw new \RuntimeException('Booking is not in a ticketable state.');
        }

        $this->recorder->recordBookingLifecycleStage($cid, $driver, self::STAGE_TICKETED, [
            'booking_reference' => $bookingReference,
            'status' => 'ticketed',
        ]);

        return new BookingData(
            status: 'ticketed',
            providerCode: $booking->providerCode,
            bookingReference: $booking->bookingReference,
            pnr: $booking->pnr,
            travelers: $booking->travelers,
            totalPrice: $booking->totalPrice,
            createdAt: $booking->createdAt,
            metadata: $booking->metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $opaqueContext
     */
    public function cancel(string $bookingReference, array $opaqueContext = [], ?string $correlationId = null, ?string $providerOverride = null): BookingData
    {
        $cid = $correlationId ?? Str::uuid()->toString();
        $driver = $this->orchestration->resolveDriver($providerOverride, null, 'booking');
        $booking = $this->orchestration->booking($driver)->cancelBooking($bookingReference, $opaqueContext);
        $this->recorder->recordBookingLifecycleStage($cid, $driver, self::STAGE_CANCELLED, [
            'booking_reference' => $bookingReference,
            'status' => $booking->status,
        ]);

        return $booking;
    }

    /**
     * @param  array<string, mixed>  $amendmentPayload
     */
    public function amend(string $bookingReference, array $amendmentPayload = [], ?string $correlationId = null, ?string $providerOverride = null): BookingData
    {
        $cid = $correlationId ?? Str::uuid()->toString();
        $driver = $this->orchestration->resolveDriver($providerOverride, null, 'booking');
        $provider = $this->orchestration->booking($driver);
        $current = $provider instanceof BookingAmendmentProviderInterface
            ? $provider->amendBooking($bookingReference, $amendmentPayload)
            : $provider->retrieveBooking($bookingReference);
        $this->recorder->recordBookingLifecycleStage($cid, $driver, self::STAGE_AMENDED, [
            'booking_reference' => $bookingReference,
            'payload_keys' => array_keys($amendmentPayload),
        ]);

        return $current;
    }
}
