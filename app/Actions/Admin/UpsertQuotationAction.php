<?php

namespace App\Actions\Admin;

use App\Models\FlightEntry;
use App\Models\HotelRate;
use App\Models\Inquiry;
use App\Models\PromoCode;
use App\Models\Quotation;
use App\Models\TransportRate;
use App\Models\VisaRate;
use App\Repositories\QuotationRepository;
use App\Services\Calculator\UmrahQuotationCalculator;
use App\Services\Marketing\PromoCodeService;
use App\Services\Modules\ModuleRuntimeConfigService;
use App\Services\Quotation\UmrahQuotationInputFactory;
use Illuminate\Support\Facades\DB;

class UpsertQuotationAction
{
    public function __construct(
        private readonly QuotationRepository $quotationRepository,
        private readonly UmrahQuotationCalculator $calculator,
        private readonly UmrahQuotationInputFactory $umrahQuotationInputFactory,
        private readonly PromoCodeService $promoCodeService,
        private readonly ModuleRuntimeConfigService $moduleRuntimeConfig,
    ) {
    }

    public function execute(array $validatedData, ?Quotation $quotation = null): Quotation
    {
        return DB::transaction(function () use ($validatedData, $quotation): Quotation {
            $makkahRate = HotelRate::with('roomType.hotel')->findOrFail($validatedData['makkah_hotel_rate_id']);
            $madinahRate = HotelRate::with('roomType.hotel')->findOrFail($validatedData['madinah_hotel_rate_id']);
            $visaRate = VisaRate::with('visaType')->findOrFail($validatedData['visa_rate_id']);
            $transportRate = TransportRate::with('transportType')->findOrFail($validatedData['transport_rate_id']);
            $flightEntry = FlightEntry::findOrFail($validatedData['flight_entry_id']);

            $pricingDefaults = $this->moduleRuntimeConfig->pricingDefaultsForServiceType('Umrah', 'b2c');
            $prepared = $validatedData;
            $prepared['markup_type'] = (string) ($validatedData['markup_type'] ?? $pricingDefaults['markup_type']);
            $prepared['markup_value'] = isset($validatedData['markup_value'])
                ? (float) $validatedData['markup_value']
                : (float) $pricingDefaults['markup_value'];
            $prepared['currency'] = (string) ($validatedData['currency'] ?? $pricingDefaults['base_currency']);

            $input = $this->umrahQuotationInputFactory->fromValidated(
                $prepared,
                $makkahRate,
                $madinahRate,
                $visaRate,
                $transportRate,
                $flightEntry
            );

            $calculated = $this->calculator->calculate($input);
            $taxAmount = $this->moduleRuntimeConfig->calculateTaxAmount('Umrah', $calculated->grandTotal);
            $manualDiscountAmount = (float) ($validatedData['discount_amount'] ?? 0);
            $grossTotal = $calculated->grandTotal + $taxAmount;
            $afterManual = max(0, $grossTotal - $manualDiscountAmount);
            $promoCodeText = trim((string) ($validatedData['promo_code'] ?? ''));
            $promo = $promoCodeText !== '' ? $this->promoCodeService->validateForAmount($promoCodeText, $afterManual) : null;
            $promoDiscountAmount = $promo instanceof PromoCode
                ? $this->promoCodeService->discountAmount($promo, $afterManual)
                : 0.0;
            $discountAmount = $manualDiscountAmount + $promoDiscountAmount;
            $finalTotal = max(0, $grossTotal - $discountAmount);

            $basePayload = [
                'agency_id' => (int) $validatedData['agency_id'],
                'user_id' => auth()->id(),
                'inquiry_id' => $validatedData['inquiry_id'] ?? null,
                'customer_name' => $validatedData['customer_name'],
                'customer_email' => $validatedData['customer_email'] ?? null,
                'customer_phone' => $validatedData['customer_phone'] ?? null,
                'travel_date' => $validatedData['travel_date'] ?? null,
                'return_date' => $validatedData['return_date'] ?? null,
                'adults' => (int) $validatedData['adults'],
                'children' => (int) ($validatedData['children'] ?? 0),
                'infants' => (int) ($validatedData['infants'] ?? 0),
                'currency' => strtoupper((string) $prepared['currency']),
                'subtotal' => $calculated->subTotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'promo_code_id' => $promo?->id,
                'promo_code' => $promo?->code,
                'promo_discount_amount' => $promoDiscountAmount,
                'total_amount' => $finalTotal,
                'status' => $validatedData['status'],
                'notes' => $validatedData['notes'] ?? null,
                'expires_at' => $validatedData['expires_at'] ?? null,
            ];

            $savedQuotation = $quotation
                ? $this->quotationRepository->update($quotation, $basePayload)
                : $this->quotationRepository->create([
                    ...$basePayload,
                    'quote_number' => $this->quotationRepository->nextQuoteNumber(),
                ]);

            $savedQuotation->items()->delete();

            $savedQuotation->items()->createMany([
                [
                    'item_type' => 'hotel_makkah',
                    'reference_type' => HotelRate::class,
                    'reference_id' => $makkahRate->id,
                    'title' => 'Makkah Hotel',
                    'description' => $makkahRate->roomType?->hotel?->name . ' - ' . $makkahRate->roomType?->name,
                    'quantity' => 1,
                    'unit_price' => $calculated->makkahHotelTotal,
                    'total_price' => $calculated->makkahHotelTotal,
                    'meta' => ['nights' => (int) $validatedData['makkah_nights'], 'basis' => $validatedData['makkah_room_basis']],
                ],
                [
                    'item_type' => 'hotel_madinah',
                    'reference_type' => HotelRate::class,
                    'reference_id' => $madinahRate->id,
                    'title' => 'Madinah Hotel',
                    'description' => $madinahRate->roomType?->hotel?->name . ' - ' . $madinahRate->roomType?->name,
                    'quantity' => 1,
                    'unit_price' => $calculated->madinahHotelTotal,
                    'total_price' => $calculated->madinahHotelTotal,
                    'meta' => ['nights' => (int) $validatedData['madinah_nights'], 'basis' => $validatedData['madinah_room_basis']],
                ],
                [
                    'item_type' => 'visa',
                    'reference_type' => VisaRate::class,
                    'reference_id' => $visaRate->id,
                    'title' => 'Visa',
                    'description' => $visaRate->visaType?->name,
                    'quantity' => 1,
                    'unit_price' => $calculated->visaTotal,
                    'total_price' => $calculated->visaTotal,
                    'meta' => ['mode' => $validatedData['visa_pricing_mode']],
                ],
                [
                    'item_type' => 'transport',
                    'reference_type' => TransportRate::class,
                    'reference_id' => $transportRate->id,
                    'title' => 'Transport',
                    'description' => $transportRate->transportType?->name,
                    'quantity' => 1,
                    'unit_price' => $calculated->transportTotal,
                    'total_price' => $calculated->transportTotal,
                    'meta' => ['mode' => $validatedData['transport_pricing_mode']],
                ],
                [
                    'item_type' => 'flight',
                    'reference_type' => FlightEntry::class,
                    'reference_id' => $flightEntry->id,
                    'title' => 'Flight',
                    'description' => "{$flightEntry->origin} - {$flightEntry->destination}",
                    'quantity' => 1,
                    'unit_price' => $calculated->flightTotal,
                    'total_price' => $calculated->flightTotal,
                    'meta' => array_filter([
                        'mode' => $validatedData['flight_pricing_mode'],
                        'integration' => $this->buildFlightIntegrationMeta($validatedData),
                    ], static fn (mixed $value): bool => $value !== null),
                ],
                [
                    'item_type' => 'extras',
                    'reference_type' => null,
                    'reference_id' => null,
                    'title' => (string) ($validatedData['extras_label'] ?? 'Extras'),
                    'description' => null,
                    'quantity' => 1,
                    'unit_price' => $calculated->extrasTotal,
                    'total_price' => $calculated->extrasTotal,
                    'meta' => ['mode' => (string) ($validatedData['extras_mode'] ?? 'fixed')],
                ],
                [
                    'item_type' => 'markup',
                    'reference_type' => null,
                    'reference_id' => null,
                    'title' => 'Markup',
                    'description' => null,
                    'quantity' => 1,
                    'unit_price' => $calculated->markupAmount,
                    'total_price' => $calculated->markupAmount,
                    'meta' => ['type' => $prepared['markup_type']],
                ],
            ]);

            if (! empty($basePayload['inquiry_id'])) {
                Inquiry::query()
                    ->whereKey($basePayload['inquiry_id'])
                    ->update(['status' => Inquiry::STATUS_QUOTED]);
            }

            return $savedQuotation->fresh(['agency', 'inquiry', 'items']);
        });
    }

    /**
     * @param  array<string, mixed>  $validatedData
     * @return array<string, mixed>|null
     */
    private function buildFlightIntegrationMeta(array $validatedData): ?array
    {
        $provider = strtolower(trim((string) ($validatedData['integration_provider'] ?? '')));
        $offerReference = trim((string) ($validatedData['integration_offer_reference'] ?? ''));
        if ($provider === '' || $offerReference === '') {
            return null;
        }

        $correlationId = trim((string) ($validatedData['integration_correlation_id'] ?? ''));
        $selectedPassengersCsv = trim((string) ($validatedData['integration_selected_passengers_csv'] ?? ''));
        $selectedPassengers = $selectedPassengersCsv === ''
            ? []
            : array_values(array_filter(array_map(
                static fn (string $value): string => trim($value),
                explode(',', $selectedPassengersCsv)
            )));

        return array_filter([
            'provider' => $provider,
            'offer_reference' => $offerReference,
            'correlation_id' => $correlationId !== '' ? $correlationId : null,
            'selected_passengers' => $selectedPassengers !== [] ? $selectedPassengers : null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
