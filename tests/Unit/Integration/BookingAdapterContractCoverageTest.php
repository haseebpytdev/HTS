<?php

namespace Tests\Unit\Integration;

use App\Contracts\Integrations\BookingAmendmentProviderInterface;
use App\Contracts\Integrations\BookingTicketingProviderInterface;
use App\Integrations\Amadeus\AmadeusBookingAdapter;
use App\Integrations\Sabre\SabreBookingAdapter;
use App\Integrations\Travelport\TravelportBookingAdapter;
use Tests\TestCase;

class BookingAdapterContractCoverageTest extends TestCase
{
    public function test_amadeus_booking_adapter_implements_ticket_and_amendment_contracts(): void
    {
        $this->assertContains(BookingTicketingProviderInterface::class, class_implements(AmadeusBookingAdapter::class));
        $this->assertContains(BookingAmendmentProviderInterface::class, class_implements(AmadeusBookingAdapter::class));
    }

    public function test_travelport_booking_adapter_implements_ticket_and_amendment_contracts(): void
    {
        $this->assertContains(BookingTicketingProviderInterface::class, class_implements(TravelportBookingAdapter::class));
        $this->assertContains(BookingAmendmentProviderInterface::class, class_implements(TravelportBookingAdapter::class));
    }

    public function test_sabre_booking_adapter_implements_ticket_and_amendment_contracts(): void
    {
        $this->assertContains(BookingTicketingProviderInterface::class, class_implements(SabreBookingAdapter::class));
        $this->assertContains(BookingAmendmentProviderInterface::class, class_implements(SabreBookingAdapter::class));
    }
}

