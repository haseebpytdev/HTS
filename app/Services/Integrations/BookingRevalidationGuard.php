<?php

namespace App\Services\Integrations;

use App\Data\Integrations\ApiErrorData;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use Illuminate\Support\Facades\Cache;

final class BookingRevalidationGuard
{
    public function recordRevalidationResult(
        string $offerReference,
        string $driver,
        string $status,
        string $correlationId,
        float $totalAmount,
        string $currency,
        array $checks = []
    ): void {
        if (! config('integrations.booking_revalidation_required', true)) {
            return;
        }

        if (! $this->isSuccessfulStatus($status)) {
            return;
        }

        $ttl = max(60, (int) config('integrations.booking_revalidation_snapshot_ttl_seconds', 1200));
        Cache::put($this->key($offerReference, $driver), [
            'offer_reference' => $offerReference,
            'provider' => $driver,
            'status' => $status,
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'correlation_id' => $correlationId,
            'seat_available' => isset($checks['seat_available']) ? (bool) $checks['seat_available'] : true,
            'fare_rules_confirmed' => isset($checks['fare_rules_confirmed']) ? (bool) $checks['fare_rules_confirmed'] : true,
            'price_validated' => isset($checks['price_validated']) ? (bool) $checks['price_validated'] : true,
            'check_details' => is_array($checks['details'] ?? null) ? $checks['details'] : null,
            'revalidated_at' => now()->toIso8601String(),
        ], now()->addSeconds($ttl));
    }

    public function enforceFreshSuccessfulRevalidation(string $offerReference, string $driver): void
    {
        if (! config('integrations.booking_revalidation_required', true)) {
            return;
        }

        $snapshot = Cache::get($this->key($offerReference, $driver));
        $snapshotCorrelationId = is_array($snapshot) ? (($snapshot['correlation_id'] ?? null) ?: null) : null;
        if (! is_array($snapshot)) {
            throw $this->failure('Missing recent successful fare confirmation for this itinerary.', null);
        }

        $revalidatedAt = isset($snapshot['revalidated_at']) ? strtotime((string) $snapshot['revalidated_at']) : false;
        if ($revalidatedAt === false) {
            throw $this->failure('Invalid fare confirmation timestamp. Please select the flight again from search results.', $snapshotCorrelationId);
        }

        $windowMinutes = max(1, (int) config('integrations.booking_revalidation_fresh_window_minutes', 15));
        $ageSeconds = now()->getTimestamp() - $revalidatedAt;
        if ($ageSeconds > ($windowMinutes * 60)) {
            throw $this->failure("Fare confirmation expired. Return to search and continue within {$windowMinutes} minutes before booking.", $snapshotCorrelationId);
        }

        if (! $this->isSuccessfulStatus((string) ($snapshot['status'] ?? ''))) {
            throw $this->failure('Latest fare confirmation with the airline was not successful.', $snapshotCorrelationId);
        }

        $this->enforcePreBookingChecks($snapshot, $snapshotCorrelationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(string $offerReference, string $driver): array
    {
        $snapshot = Cache::get($this->key($offerReference, $driver));

        return is_array($snapshot) ? $snapshot : [];
    }

    private function key(string $offerReference, string $driver): string
    {
        return 'integrations:booking_revalidation:'.sha1($driver.'|'.$offerReference);
    }

    private function isSuccessfulStatus(string $status): bool
    {
        return ! in_array(strtolower($status), ['unavailable', 'not_found', 'not_implemented', 'unmapped', 'failed'], true);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function enforcePreBookingChecks(array $snapshot, ?string $correlationId): void
    {
        if ((bool) config('integrations.booking_precheck_require_price_validation', true)
            && ! (bool) ($snapshot['price_validated'] ?? false)
        ) {
            throw $this->failure('Price validation check failed before booking.', $correlationId);
        }
        if ((bool) config('integrations.booking_precheck_require_seat_availability', true)
            && ! (bool) ($snapshot['seat_available'] ?? false)
        ) {
            throw $this->failure('Seat availability check failed before booking.', $correlationId);
        }
        if ((bool) config('integrations.booking_precheck_require_fare_rules_confirmed', true)
            && ! (bool) ($snapshot['fare_rules_confirmed'] ?? false)
        ) {
            throw $this->failure('Fare-rules confirmation check failed before booking.', $correlationId);
        }
    }

    private function failure(string $message, ?string $correlationId): SupplierIntegrationException
    {
        return new SupplierIntegrationException(
            message: $message,
            supplierCode: 'REVALIDATION_REQUIRED',
            normalizedCode: 'fresh_revalidation_required',
            supplierContext: ['guard' => 'booking_revalidation'],
            apiError: new ApiErrorData(
                code: 'fresh_revalidation_required',
                message: $message,
                supplierCode: 'REVALIDATION_REQUIRED',
                correlationId: $correlationId,
                httpStatus: 422,
            ),
        );
    }
}
