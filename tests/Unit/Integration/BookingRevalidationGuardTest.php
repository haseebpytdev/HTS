<?php

namespace Tests\Unit\Integration;

use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use App\Services\Integrations\BookingRevalidationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BookingRevalidationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_blocks_when_no_recent_revalidation_exists(): void
    {
        Cache::flush();
        config(['integrations.booking_revalidation_required' => true]);

        $guard = app(BookingRevalidationGuard::class);
        $this->expectException(SupplierIntegrationException::class);
        $guard->enforceFreshSuccessfulRevalidation('offer-1', 'travelport');
    }

    public function test_it_allows_booking_within_freshness_window_after_successful_revalidation(): void
    {
        Cache::flush();
        config([
            'integrations.booking_revalidation_required' => true,
            'integrations.booking_revalidation_fresh_window_minutes' => 15,
        ]);
        $guard = app(BookingRevalidationGuard::class);

        $guard->recordRevalidationResult('offer-2', 'sabre', 'confirmed', 'corr-1', 120.50, 'USD');
        $guard->enforceFreshSuccessfulRevalidation('offer-2', 'sabre');

        $this->assertTrue(true);
    }

    public function test_it_blocks_when_revalidation_is_expired(): void
    {
        Cache::flush();
        config([
            'integrations.booking_revalidation_required' => true,
            'integrations.booking_revalidation_fresh_window_minutes' => 10,
        ]);
        $guard = app(BookingRevalidationGuard::class);

        $guard->recordRevalidationResult('offer-3', 'amadeus', 'confirmed', 'corr-2', 220.00, 'USD');
        $this->travel(11)->minutes();

        $this->expectException(SupplierIntegrationException::class);
        $guard->enforceFreshSuccessfulRevalidation('offer-3', 'amadeus');
    }
}
