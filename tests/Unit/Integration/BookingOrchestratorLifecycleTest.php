<?php

namespace Tests\Unit\Integration;

use App\Contracts\Integrations\BookingAmendmentProviderInterface;
use App\Contracts\Integrations\BookingProviderInterface;
use App\Contracts\Integrations\BookingTicketingProviderInterface;
use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\BookingData;
use App\Data\Integrations\NormalizedPayloadMetadata;
use App\Data\Integrations\PriceBreakdownData;
use App\Data\Integrations\TravelerData;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Integrations\Stub\StubBookingAdapter;
use App\Models\IntegrationLog;
use App\Services\Integrations\BookingOrchestrator;
use App\Services\Integrations\BookingRevalidationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BookingOrchestratorLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'integrations.driver' => 'stub',
            'integrations.supported_drivers' => ['stub'],
            'integrations.booking_revalidation_required' => true,
            'integrations.booking_precheck_require_price_validation' => true,
            'integrations.booking_precheck_require_seat_availability' => true,
            'integrations.booking_precheck_require_fare_rules_confirmed' => true,
            'integrations.stub_scenario' => 'booking_success',
        ]);
    }

    public function test_create_blocks_when_seat_availability_precheck_fails(): void
    {
        $guard = app(BookingRevalidationGuard::class);
        $guard->recordRevalidationResult('OFFER-1', 'stub', 'confirmed', 'corr-1', 120.0, 'USD', [
            'seat_available' => false,
            'fare_rules_confirmed' => true,
            'price_validated' => true,
        ]);

        $this->expectException(SupplierIntegrationException::class);
        app(BookingOrchestrator::class)->create(
            new BookingCreateRequestData('OFFER-1', [new TravelerData('adult', 'Ali', 'Khan')]),
            'cid-booking-1',
            'stub'
        );
    }

    public function test_create_records_booking_lifecycle_stages(): void
    {
        $guard = app(BookingRevalidationGuard::class);
        $guard->recordRevalidationResult('OFFER-2', 'stub', 'confirmed', 'corr-2', 150.0, 'USD', [
            'seat_available' => true,
            'fare_rules_confirmed' => true,
            'price_validated' => true,
        ]);

        $booking = app(BookingOrchestrator::class)->create(
            new BookingCreateRequestData('OFFER-2', [new TravelerData('adult', 'Sara', 'Khan')]),
            'cid-booking-2',
            'stub'
        );

        $this->assertContains($booking->status, ['confirmed', 'not_implemented']);
        $this->assertDatabaseHas('integration_logs', [
            'provider' => 'stub',
            'log_type' => 'booking_lifecycle_stage',
        ]);

        $stages = IntegrationLog::query()
            ->where('provider', 'stub')
            ->where('log_type', 'booking_lifecycle_stage')
            ->pluck('payload')
            ->all();
        $stageNames = array_values(array_filter(array_map(static fn ($payload) => $payload['stage'] ?? null, $stages)));

        $this->assertContains('revalidation_passed', $stageNames);
        $this->assertContains('pnr_created', $stageNames);
        $this->assertContains('ticketing_pending', $stageNames);
    }

    public function test_ticket_cancel_retrieve_and_amend_workflow_methods(): void
    {
        $this->app->bind(StubBookingAdapter::class, fn () => new class implements BookingProviderInterface
        {
            public function providerCode(): string
            {
                return 'stub';
            }

            public function createBooking(BookingCreateRequestData $request): BookingData
            {
                return new BookingData('confirmed', 'stub', 'BKG-1', 'PNR-1', metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'booking'));
            }

            public function retrieveBooking(string $bookingReference): BookingData
            {
                return new BookingData(
                    status: 'confirmed',
                    providerCode: 'stub',
                    bookingReference: $bookingReference,
                    pnr: 'PNR-RET-1',
                    totalPrice: new PriceBreakdownData('USD', 100, 20, 0, 120, 'confirmed'),
                    metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'booking'),
                );
            }

            public function cancelBooking(string $bookingReference, array $opaqueContext = []): BookingData
            {
                return new BookingData('cancelled', 'stub', $bookingReference, 'PNR-CAN-1', metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'booking'));
            }
        });

        $orchestrator = app(BookingOrchestrator::class);
        $retrieved = $orchestrator->retrieve('BKG-55', 'cid-booking-3', 'stub');
        $ticketed = $orchestrator->ticket('BKG-55', 'cid-booking-4', 'stub');
        $cancelled = $orchestrator->cancel('BKG-55', [], 'cid-booking-5', 'stub');
        $amended = $orchestrator->amend('BKG-55', ['change' => 'name'], 'cid-booking-6', 'stub');

        $this->assertSame('confirmed', $retrieved->status);
        $this->assertSame('ticketed', $ticketed->status);
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('confirmed', $amended->status);
    }

    public function test_ticket_and_amend_use_provider_specific_contracts_when_available(): void
    {
        $this->app->bind(StubBookingAdapter::class, fn () => new class implements BookingProviderInterface, BookingTicketingProviderInterface, BookingAmendmentProviderInterface
        {
            public function providerCode(): string
            {
                return 'stub';
            }

            public function createBooking(BookingCreateRequestData $request): BookingData
            {
                return new BookingData('confirmed', 'stub', 'BKG-1', 'PNR-1', metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'booking'));
            }

            public function retrieveBooking(string $bookingReference): BookingData
            {
                return new BookingData(
                    status: 'pending',
                    providerCode: 'stub',
                    bookingReference: $bookingReference,
                    metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'booking'),
                );
            }

            public function cancelBooking(string $bookingReference, array $opaqueContext = []): BookingData
            {
                return new BookingData('cancelled', 'stub', $bookingReference, 'PNR-CAN-1', metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'booking'));
            }

            public function ticketBooking(string $bookingReference, array $opaqueContext = []): BookingData
            {
                return new BookingData('ticketed', 'stub', $bookingReference, 'PNR-TKT-1', metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'booking'));
            }

            public function amendBooking(string $bookingReference, array $amendmentPayload = []): BookingData
            {
                return new BookingData('amended', 'stub', $bookingReference, 'PNR-AMD-1', metadata: NormalizedPayloadMetadata::forSchemaKey('stub', 'booking'));
            }
        });

        $orchestrator = app(BookingOrchestrator::class);
        $ticketed = $orchestrator->ticket('BKG-77', 'cid-booking-7', 'stub');
        $amended = $orchestrator->amend('BKG-77', ['change' => 'date'], 'cid-booking-8', 'stub');

        $this->assertSame('ticketed', $ticketed->status);
        $this->assertSame('amended', $amended->status);
    }
}

