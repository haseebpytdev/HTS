<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\FlightEntry;
use App\Models\HotelRate;
use App\Models\PromoCode;
use App\Models\Quotation;
use App\Models\ServiceModule;
use App\Models\ServiceModulePricingRule;
use App\Models\ServiceModuleTaxRule;
use App\Models\TransportRate;
use App\Models\User;
use App\Models\VisaRate;
use App\Services\Calculator\UmrahQuotationCalculator;
use App\Services\Marketing\PromoCodeService;
use App\Services\Quotation\UmrahQuotationInputFactory;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUmrahQuotationDependencies;
use Tests\TestCase;

class AdminQuotationFlowTest extends TestCase
{
    use CreatesUmrahQuotationDependencies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_create_quotation_via_store_and_persist_calculated_totals(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $deps = $this->createUmrahQuotationDependencies();

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Phase 15 Client',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'PKR',
            'status' => 'draft',
            'makkah_hotel_rate_id' => $deps['makkah_hotel_rate_id'],
            'makkah_nights' => 5,
            'makkah_room_basis' => 'double',
            'madinah_hotel_rate_id' => $deps['madinah_hotel_rate_id'],
            'madinah_nights' => 4,
            'madinah_room_basis' => 'double',
            'visa_rate_id' => $deps['visa_rate_id'],
            'visa_pricing_mode' => 'per_person',
            'transport_rate_id' => $deps['transport_rate_id'],
            'transport_pricing_mode' => 'fixed',
            'flight_entry_id' => $deps['flight_entry_id'],
            'flight_pricing_mode' => 'per_person',
            'markup_type' => 'percentage',
            'markup_value' => 10,
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.quotations.store'), $payload);

        $response->assertRedirect();

        $quotation = Quotation::query()->where('customer_name', 'Phase 15 Client')->first();
        $this->assertNotNull($quotation);
        $this->assertSame($deps['agency']->id, $quotation->agency_id);
        $this->assertGreaterThan(0, (float) $quotation->total_amount);
        $this->assertGreaterThan(0, $quotation->items()->count());

        $this->assertPersistedQuotationMatchesCalculator($payload);
    }

    public function test_admin_quotation_index_filters_by_agency_id(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $deps = $this->createUmrahQuotationDependencies();
        $otherAgency = Agency::query()->create(['name' => 'Other', 'code' => 'OTH'.substr(uniqid(), -4), 'is_active' => true]);

        Quotation::query()->create([
            'agency_id' => $deps['agency']->id,
            'user_id' => $admin->id,
            'quote_number' => 'QTN-FILTER-A',
            'customer_name' => 'Keep',
            'currency' => 'PKR',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 100,
            'status' => 'draft',
        ]);

        Quotation::query()->create([
            'agency_id' => $otherAgency->id,
            'user_id' => $admin->id,
            'quote_number' => 'QTN-FILTER-B',
            'customer_name' => 'Hide',
            'currency' => 'PKR',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'subtotal' => 200,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 200,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.quotations.index', ['agency_id' => $deps['agency']->id]))
            ->assertOk()
            ->assertSee('QTN-FILTER-A')
            ->assertDontSee('QTN-FILTER-B');
    }

    public function test_persisted_line_items_match_calculator_components_for_mixed_room_bases(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Room Basis Mix Client',
            'adults' => 3,
            'children' => 0,
            'infants' => 0,
            'currency' => 'PKR',
            'status' => 'draft',
            'makkah_hotel_rate_id' => $deps['makkah_hotel_rate_id'],
            'makkah_nights' => 4,
            'makkah_room_basis' => 'triple',
            'madinah_hotel_rate_id' => $deps['madinah_hotel_rate_id'],
            'madinah_nights' => 3,
            'madinah_room_basis' => 'single',
            'visa_rate_id' => $deps['visa_rate_id'],
            'visa_pricing_mode' => 'per_person',
            'transport_rate_id' => $deps['transport_rate_id'],
            'transport_pricing_mode' => 'fixed',
            'flight_entry_id' => $deps['flight_entry_id'],
            'flight_pricing_mode' => 'per_person',
            'markup_type' => 'fixed',
            'markup_value' => 12000,
        ];

        $this->actingAs($admin)->post(route('admin.quotations.store'), $payload)->assertRedirect();

        $this->assertPersistedQuotationMatchesCalculator($payload);
    }

    public function test_children_child_occupancy_factor_and_optional_extras_match_calculator(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Children Extras Client',
            'adults' => 2,
            'children' => 2,
            'infants' => 0,
            'currency' => 'PKR',
            'status' => 'draft',
            'child_occupancy_factor' => 0.5,
            'makkah_hotel_rate_id' => $deps['makkah_hotel_rate_id'],
            'makkah_nights' => 3,
            'makkah_room_basis' => 'double',
            'madinah_hotel_rate_id' => $deps['madinah_hotel_rate_id'],
            'madinah_nights' => 2,
            'madinah_room_basis' => 'double',
            'visa_rate_id' => $deps['visa_rate_id'],
            'visa_pricing_mode' => 'per_person',
            'transport_rate_id' => $deps['transport_rate_id'],
            'transport_pricing_mode' => 'fixed',
            'flight_entry_id' => $deps['flight_entry_id'],
            'flight_pricing_mode' => 'per_person',
            'extras_label' => 'Ziyarah add-on',
            'extras_amount' => 5000,
            'extras_mode' => 'per_person',
            'markup_type' => 'percentage',
            'markup_value' => 8,
        ];

        $this->actingAs($admin)->post(route('admin.quotations.store'), $payload)->assertRedirect();

        $this->assertPersistedQuotationMatchesCalculator($payload);
    }

    public function test_manual_discount_and_promo_discount_propagate_to_totals(): void
    {
        PromoCode::query()->create([
            'code' => 'RF43-PCT10',
            'description' => 'RF4.3 test promo',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'max_discount_amount' => null,
            'starts_at' => null,
            'expires_at' => null,
            'usage_limit' => null,
            'usage_count' => 0,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Promo Discount Client',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'PKR',
            'status' => 'draft',
            'makkah_hotel_rate_id' => $deps['makkah_hotel_rate_id'],
            'makkah_nights' => 2,
            'makkah_room_basis' => 'double',
            'madinah_hotel_rate_id' => $deps['madinah_hotel_rate_id'],
            'madinah_nights' => 2,
            'madinah_room_basis' => 'double',
            'visa_rate_id' => $deps['visa_rate_id'],
            'visa_pricing_mode' => 'per_person',
            'transport_rate_id' => $deps['transport_rate_id'],
            'transport_pricing_mode' => 'fixed',
            'flight_entry_id' => $deps['flight_entry_id'],
            'flight_pricing_mode' => 'per_person',
            'markup_type' => 'percentage',
            'markup_value' => 10,
            'discount_amount' => 25000,
            'promo_code' => 'RF43-PCT10',
        ];

        $this->actingAs($admin)->post(route('admin.quotations.store'), $payload)->assertRedirect();

        $this->assertPersistedQuotationMatchesCalculator($payload);

        $quotation = Quotation::query()->where('customer_name', 'Promo Discount Client')->firstOrFail();
        $this->assertGreaterThan(0, (float) $quotation->promo_discount_amount);
        $this->assertSame('RF43-PCT10', $quotation->promo_code);
    }

    public function test_converted_booking_copies_quotation_financial_totals(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'Quote To Booking Client',
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'currency' => 'PKR',
            'status' => 'draft',
            'child_occupancy_factor' => 1.0,
            'makkah_hotel_rate_id' => $deps['makkah_hotel_rate_id'],
            'makkah_nights' => 3,
            'makkah_room_basis' => 'triple',
            'madinah_hotel_rate_id' => $deps['madinah_hotel_rate_id'],
            'madinah_nights' => 2,
            'madinah_room_basis' => 'double',
            'visa_rate_id' => $deps['visa_rate_id'],
            'visa_pricing_mode' => 'per_person',
            'transport_rate_id' => $deps['transport_rate_id'],
            'transport_pricing_mode' => 'fixed',
            'flight_entry_id' => $deps['flight_entry_id'],
            'flight_pricing_mode' => 'per_person',
            'extras_label' => 'Insurance',
            'extras_amount' => 9000,
            'extras_mode' => 'fixed',
            'markup_type' => 'fixed',
            'markup_value' => 15000,
            'discount_amount' => 5000,
        ];

        $this->actingAs($admin)->post(route('admin.quotations.store'), $payload)->assertRedirect();
        $this->assertPersistedQuotationMatchesCalculator($payload);

        $quotation = Quotation::query()->where('customer_name', 'Quote To Booking Client')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.quotations.bookings.store', $quotation))
            ->assertRedirect();

        $booking = Booking::query()->where('quotation_id', $quotation->id)->firstOrFail();

        $this->assertEqualsWithDelta((float) $quotation->subtotal, (float) $booking->subtotal, 0.02);
        $this->assertEqualsWithDelta((float) $quotation->discount_amount, (float) $booking->discount_amount, 0.02);
        $this->assertEqualsWithDelta((float) $quotation->total_amount, (float) $booking->total_amount, 0.02);
        $this->assertSame($quotation->currency, $booking->currency);
    }

    public function test_quotation_uses_db_backed_module_markup_and_tax_defaults_when_markup_not_provided(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $deps = $this->createUmrahQuotationDependencies();

        $module = ServiceModule::query()->create([
            'module_key' => 'umrah_package_engine',
            'code' => 'umrah_package_engine',
            'name' => 'Umrah Engine',
            'provider' => 'internal',
            'provider_name' => 'Internal',
            'service_type' => 'Umrah',
            'environment' => 'sandbox',
            'is_active' => true,
            'is_default' => true,
            'status' => 'active',
            'connection_status' => 'connected',
            'supported_operations_json' => ['pricing'],
            'sort_order' => 1,
        ]);

        ServiceModulePricingRule::query()->create([
            'service_module_id' => $module->id,
            'markup_type_b2b' => 'percentage',
            'markup_value_b2b' => 0,
            'markup_type_b2c' => 'percentage',
            'markup_value_b2c' => 12,
            'base_currency_code' => 'PKR',
        ]);
        ServiceModuleTaxRule::query()->create([
            'service_module_id' => $module->id,
            'tax_type' => 'percentage',
            'tax_value' => 5,
        ]);

        $payload = [
            'agency_id' => $deps['agency']->id,
            'customer_name' => 'DB Backed Defaults Client',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'PKR',
            'status' => 'draft',
            'makkah_hotel_rate_id' => $deps['makkah_hotel_rate_id'],
            'makkah_nights' => 2,
            'makkah_room_basis' => 'double',
            'madinah_hotel_rate_id' => $deps['madinah_hotel_rate_id'],
            'madinah_nights' => 2,
            'madinah_room_basis' => 'double',
            'visa_rate_id' => $deps['visa_rate_id'],
            'visa_pricing_mode' => 'per_person',
            'transport_rate_id' => $deps['transport_rate_id'],
            'transport_pricing_mode' => 'fixed',
            'flight_entry_id' => $deps['flight_entry_id'],
            'flight_pricing_mode' => 'per_person',
        ];

        $this->actingAs($admin)->post(route('admin.quotations.store'), $payload)->assertRedirect();

        $quotation = Quotation::query()->where('customer_name', 'DB Backed Defaults Client')->firstOrFail();
        $this->assertGreaterThan(0, (float) $quotation->tax_amount);
        $markupItem = $quotation->items()->where('item_type', 'markup')->first();
        $this->assertNotNull($markupItem);
        $this->assertSame('percentage', (string) data_get($markupItem?->meta, 'type'));
    }

    /**
     * Mirrors discount stacking in {@see \App\Actions\Admin\UpsertQuotationAction}.
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertPersistedQuotationMatchesCalculator(array $payload): void
    {
        $customerName = $payload['customer_name'];
        $quotation = Quotation::query()->where('customer_name', $customerName)->firstOrFail();

        $makkahRate = HotelRate::with('roomType.hotel')->findOrFail($payload['makkah_hotel_rate_id']);
        $madinahRate = HotelRate::with('roomType.hotel')->findOrFail($payload['madinah_hotel_rate_id']);
        $visaRate = VisaRate::with('visaType')->findOrFail($payload['visa_rate_id']);
        $transportRate = TransportRate::with('transportType')->findOrFail($payload['transport_rate_id']);
        $flightEntry = FlightEntry::findOrFail($payload['flight_entry_id']);

        $input = $this->app->make(UmrahQuotationInputFactory::class)->fromValidated(
            $payload,
            $makkahRate,
            $madinahRate,
            $visaRate,
            $transportRate,
            $flightEntry
        );

        $calculated = $this->app->make(UmrahQuotationCalculator::class)->calculate($input);

        $manualDiscountAmount = (float) ($payload['discount_amount'] ?? 0);
        $afterManual = max(0, $calculated->grandTotal - $manualDiscountAmount);
        $promoCodeText = trim((string) ($payload['promo_code'] ?? ''));
        $promoCodeService = $this->app->make(PromoCodeService::class);
        $promo = $promoCodeText !== ''
            ? $promoCodeService->validateForAmount($promoCodeText, $afterManual)
            : null;
        $promoDiscountAmount = $promo !== null
            ? $promoCodeService->discountAmount($promo, $afterManual)
            : 0.0;
        $discountAmount = $manualDiscountAmount + $promoDiscountAmount;
        $finalTotal = max(0, $calculated->grandTotal - $discountAmount);

        $this->assertEqualsWithDelta(
            $calculated->subTotal,
            (float) $quotation->subtotal,
            0.02,
            'Quotation subtotal should match calculator subTotal (pre-discount components).'
        );
        $this->assertEqualsWithDelta(
            $discountAmount,
            (float) $quotation->discount_amount,
            0.02,
            'Quotation discount_amount should equal manual + promo discounts.'
        );
        $this->assertEqualsWithDelta(
            $promoDiscountAmount,
            (float) $quotation->promo_discount_amount,
            0.02,
            'promo_discount_amount should match PromoCodeService output.'
        );
        $this->assertEqualsWithDelta(
            $finalTotal,
            (float) $quotation->total_amount,
            0.02,
            'Quotation total_amount should match grand total minus discounts.'
        );

        $items = $quotation->items()->get()->keyBy('item_type');

        $this->assertEqualsWithDelta(
            $calculated->makkahHotelTotal,
            (float) $items->get('hotel_makkah')->total_price,
            0.02
        );
        $this->assertEqualsWithDelta(
            $calculated->madinahHotelTotal,
            (float) $items->get('hotel_madinah')->total_price,
            0.02
        );
        $this->assertEqualsWithDelta($calculated->visaTotal, (float) $items->get('visa')->total_price, 0.02);
        $this->assertEqualsWithDelta($calculated->transportTotal, (float) $items->get('transport')->total_price, 0.02);
        $this->assertEqualsWithDelta($calculated->flightTotal, (float) $items->get('flight')->total_price, 0.02);
        $this->assertEqualsWithDelta($calculated->extrasTotal, (float) $items->get('extras')->total_price, 0.02);
        $this->assertEqualsWithDelta($calculated->markupAmount, (float) $items->get('markup')->total_price, 0.02);
    }
}
