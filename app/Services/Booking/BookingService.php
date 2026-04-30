<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Quotation;
use App\Models\Traveler;
use App\Repositories\BookingRepository;
use App\Services\Finance\FinanceSettingsService;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly BookingLifecycleManager $lifecycleManager,
        private readonly FinanceSettingsService $financeSettings
    ) {
    }

    /**
     * Create a draft booking from a quotation (line items + customer snapshot).
     */
    public function createDraftFromQuotation(Quotation $quotation): Booking
    {
        return DB::transaction(function () use ($quotation): Booking {
            $quotation->load('items');

            $booking = Booking::query()->create([
                'quotation_id' => $quotation->id,
                'agency_id' => $quotation->agency_id,
                'user_id' => auth()->id(),
                'booking_number' => $this->bookingRepository->nextBookingNumber(),
                'status' => BookingStatus::Draft->value,
                'customer_name' => $quotation->customer_name,
                'customer_email' => $quotation->customer_email,
                'customer_phone' => $quotation->customer_phone,
                'travel_date' => $quotation->travel_date,
                'return_date' => $quotation->return_date,
                'subtotal' => $quotation->subtotal,
                'tax_amount' => $quotation->tax_amount,
                'discount_amount' => $quotation->discount_amount,
                'promo_code_id' => $quotation->promo_code_id,
                'promo_code' => $quotation->promo_code,
                'promo_discount_amount' => $quotation->promo_discount_amount,
                'total_amount' => $quotation->total_amount,
                'currency' => $quotation->currency,
                'payment_status' => 'unpaid',
            ]);

            $sort = 0;
            foreach ($quotation->items as $line) {
                $booking->items()->create([
                    'item_type' => $line->item_type,
                    'reference_type' => $line->reference_type,
                    'reference_id' => $line->reference_id,
                    'title' => $line->title,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'total_price' => $line->total_price,
                    'meta' => $line->meta,
                    'sort_order' => $sort++,
                ]);
            }

            $this->seedSupplierBookingRequestFromQuotation($booking, $quotation);
            $this->seedPlaceholderTravelers($booking, (int) $quotation->adults, (int) $quotation->children, (int) $quotation->infants);

            $this->lifecycleManager->initializeFromQuotation($booking->fresh());

            return $booking->fresh(['items', 'travelers', 'quotation', 'agency']);
        });
    }

    /**
     * Ensure invoice_number exists (idempotent).
     */
    public function ensureInvoiceIssued(Booking $booking): Booking
    {
        if ($booking->invoice_number) {
            return $booking;
        }

        DB::transaction(function () use ($booking): void {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->invoice_number) {
                return;
            }

            $locked->forceFill([
                'invoice_number' => $this->financeSettings->nextInvoiceNumber(),
                'invoice_issued_at' => now(),
            ])->save();
        });

        $fresh = $booking->fresh();
        $this->lifecycleManager->record(
            $fresh,
            $fresh->status,
            $fresh->status,
            'invoice_issued',
            null,
            ['invoice_number' => $fresh->invoice_number]
        );

        return $fresh;
    }

    /**
     * Replace travelers from validated payload rows.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncTravelers(Booking $booking, array $rows): void
    {
        $booking->travelers()->delete();

        foreach ($rows as $i => $row) {
            Traveler::query()->create([
                'booking_id' => $booking->id,
                'first_name' => (string) $row['first_name'],
                'last_name' => (string) $row['last_name'],
                'date_of_birth' => $row['date_of_birth'] ?? null,
                'passport_no' => $row['passport_no'] ?? null,
                'nationality' => $row['nationality'] ?? null,
                'traveler_type' => (string) ($row['traveler_type'] ?? Traveler::TYPE_ADULT),
                'sort_order' => $i,
            ]);
        }
    }

    private function seedPlaceholderTravelers(Booking $booking, int $adults, int $children, int $infants): void
    {
        $order = 0;
        for ($i = 1; $i <= max(0, $adults); $i++) {
            $booking->travelers()->create([
                'first_name' => 'Adult',
                'last_name' => (string) $i,
                'traveler_type' => Traveler::TYPE_ADULT,
                'sort_order' => $order++,
            ]);
        }
        for ($i = 1; $i <= max(0, $children); $i++) {
            $booking->travelers()->create([
                'first_name' => 'Child',
                'last_name' => (string) $i,
                'traveler_type' => Traveler::TYPE_CHILD,
                'sort_order' => $order++,
            ]);
        }
        for ($i = 1; $i <= max(0, $infants); $i++) {
            $booking->travelers()->create([
                'first_name' => 'Infant',
                'last_name' => (string) $i,
                'traveler_type' => Traveler::TYPE_INFANT,
                'sort_order' => $order++,
            ]);
        }
    }

    private function seedSupplierBookingRequestFromQuotation(Booking $booking, Quotation $quotation): void
    {
        $flightItem = $quotation->items->firstWhere('item_type', 'flight');
        $integration = is_array(data_get($flightItem?->meta, 'integration'))
            ? data_get($flightItem?->meta, 'integration')
            : null;
        if (! is_array($integration)) {
            return;
        }

        $provider = strtolower(trim((string) ($integration['provider'] ?? '')));
        $offerReference = trim((string) ($integration['offer_reference'] ?? ''));
        if ($provider === '' || $offerReference === '') {
            return;
        }

        $selectedPassengers = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), is_array($integration['selected_passengers'] ?? null) ? $integration['selected_passengers'] : []),
            static fn (string $value): bool => $value !== ''
        ));

        $notes = $this->decodeInternalNotes((string) ($booking->internal_notes ?? ''));
        $notes['supplier_booking_request'] = array_filter([
            'provider' => $provider,
            'offer_reference' => $offerReference,
            'correlation_id' => trim((string) ($integration['correlation_id'] ?? '')) !== '' ? trim((string) $integration['correlation_id']) : ('quotation-'.$quotation->id.'-booking-'.$booking->id),
            'selected_passengers' => $selectedPassengers !== [] ? $selectedPassengers : null,
            'source' => 'quotation_flight_selection',
        ], static fn (mixed $value): bool => $value !== null);

        $booking->forceFill([
            'internal_notes' => (string) json_encode($notes, JSON_UNESCAPED_SLASHES),
        ])->save();
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
}
