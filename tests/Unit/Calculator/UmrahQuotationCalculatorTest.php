<?php

namespace Tests\Unit\Calculator;

use App\Data\Calculator\PricingComponentInput;
use App\Data\Calculator\UmrahHotelStayInput;
use App\Data\Calculator\UmrahQuotationInput;
use App\Services\Calculator\UmrahQuotationCalculator;
use App\Services\Pricing\ComponentPricingService;
use App\Services\Pricing\RoomPricingService;
use PHPUnit\Framework\TestCase;

class UmrahQuotationCalculatorTest extends TestCase
{
    public function test_it_calculates_quotation_with_percentage_markup(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 2,
            children: 1,
            makkahHotel: new UmrahHotelStayInput('Makkah', 5, 30000, 'triple'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 4, 22000, 'triple'),
            visaCost: new PricingComponentInput('Visa', 45000, 'per_person'),
            transportCost: new PricingComponentInput('Transport', 25000, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 120000, 'per_person'),
            extras: [
                new PricingComponentInput('Laundry', 5000, 'fixed'),
                new PricingComponentInput('Ziyarat', 3000, 'per_person'),
            ],
            markupType: 'percentage',
            markupValue: 10
        );

        $result = $calculator->calculate($input);

        $this->assertSame(150000.0, $result->makkahHotelTotal);
        $this->assertSame(88000.0, $result->madinahHotelTotal);
        $this->assertSame(772000.0, $result->subTotal);
        $this->assertSame(77200.0, $result->markupAmount);
        $this->assertSame(849200.0, $result->grandTotal);
        $this->assertSame(283066.67, $result->perPersonTotal);
    }

    public function test_it_calculates_quotation_with_fixed_markup(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 4,
            children: 0,
            makkahHotel: new UmrahHotelStayInput('Makkah', 4, 26000, 'double'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 3, 21000, 'double'),
            visaCost: new PricingComponentInput('Visa', 40000, 'per_person'),
            transportCost: new PricingComponentInput('Transport', 30000, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 115000, 'per_person'),
            extras: [],
            markupType: 'fixed',
            markupValue: 50000
        );

        $result = $calculator->calculate($input);

        $this->assertSame(208000.0, $result->makkahHotelTotal);
        $this->assertSame(126000.0, $result->madinahHotelTotal);
        $this->assertSame(984000.0, $result->subTotal);
        $this->assertSame(50000.0, $result->markupAmount);
        $this->assertSame(1034000.0, $result->grandTotal);
        $this->assertSame(258500.0, $result->perPersonTotal);
    }

    public function test_it_uses_explicit_rooms_and_child_occupancy_factor(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 2,
            children: 2,
            makkahHotel: new UmrahHotelStayInput('Makkah', 3, 20000, 'quad', roomsCount: 2),
            madinahHotel: new UmrahHotelStayInput('Madinah', 3, 18000, 'quad'),
            visaCost: new PricingComponentInput('Visa', 0, 'fixed'),
            transportCost: new PricingComponentInput('Transport', 0, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 0, 'fixed'),
            extras: [],
            markupType: 'fixed',
            markupValue: 0,
            childOccupancyFactor: 0.5
        );

        $result = $calculator->calculate($input);

        $this->assertSame(120000.0, $result->makkahHotelTotal);
        $this->assertSame(54000.0, $result->madinahHotelTotal);
        $this->assertSame(174000.0, $result->grandTotal);
        $this->assertSame(43500.0, $result->perPersonTotal);
    }

    public function test_it_applies_per_person_visa_for_each_traveler(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 3,
            children: 0,
            makkahHotel: new UmrahHotelStayInput('Makkah', 1, 10000, 'triple'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 1, 10000, 'triple'),
            visaCost: new PricingComponentInput('Visa', 5000, 'per_person'),
            transportCost: new PricingComponentInput('Transport', 0, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 0, 'fixed'),
            extras: [],
            markupType: 'fixed',
            markupValue: 0,
        );

        $result = $calculator->calculate($input);

        $this->assertSame(15000.0, $result->visaTotal);
        $this->assertSame(35000.0, $result->grandTotal);
    }

    public function test_single_room_basis_counts_one_room_for_one_adult(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 1,
            children: 0,
            makkahHotel: new UmrahHotelStayInput('Makkah', 4, 12000, 'single'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 0, 0, 'single'),
            visaCost: new PricingComponentInput('Visa', 0, 'fixed'),
            transportCost: new PricingComponentInput('Transport', 0, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 0, 'fixed'),
            extras: [],
            markupType: 'fixed',
            markupValue: 0
        );

        $result = $calculator->calculate($input);

        $this->assertSame(48000.0, $result->makkahHotelTotal);
        $this->assertSame(0.0, $result->madinahHotelTotal);
        $this->assertSame(48000.0, $result->grandTotal);
    }

    public function test_triple_room_basis_fits_three_adults_in_one_room(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 3,
            children: 0,
            makkahHotel: new UmrahHotelStayInput('Makkah', 2, 18000, 'triple'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 1, 9000, 'triple'),
            visaCost: new PricingComponentInput('Visa', 0, 'fixed'),
            transportCost: new PricingComponentInput('Transport', 0, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 0, 'fixed'),
            extras: [],
            markupType: 'fixed',
            markupValue: 0
        );

        $result = $calculator->calculate($input);

        $this->assertSame(36000.0, $result->makkahHotelTotal);
        $this->assertSame(9000.0, $result->madinahHotelTotal);
        $this->assertSame(45000.0, $result->grandTotal);
    }

    public function test_quad_basis_uses_two_rooms_for_five_adults(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 5,
            children: 0,
            makkahHotel: new UmrahHotelStayInput('Makkah', 2, 10000, 'quad'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 0, 0, 'quad'),
            visaCost: new PricingComponentInput('Visa', 0, 'fixed'),
            transportCost: new PricingComponentInput('Transport', 0, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 0, 'fixed'),
            extras: [],
            markupType: 'fixed',
            markupValue: 0
        );

        $result = $calculator->calculate($input);

        $this->assertSame(40000.0, $result->makkahHotelTotal);
    }

    public function test_double_basis_two_adults_two_children_full_factor_uses_two_rooms(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 2,
            children: 2,
            makkahHotel: new UmrahHotelStayInput('Makkah', 3, 7000, 'double'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 0, 0, 'double'),
            visaCost: new PricingComponentInput('Visa', 0, 'fixed'),
            transportCost: new PricingComponentInput('Transport', 0, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 0, 'fixed'),
            extras: [],
            markupType: 'fixed',
            markupValue: 0,
            childOccupancyFactor: 1.0
        );

        $result = $calculator->calculate($input);

        $this->assertSame(42000.0, $result->makkahHotelTotal);
    }

    public function test_double_basis_with_reduced_child_occupancy_factor_changes_room_count(): void
    {
        $calculator = $this->calculator();

        $inputFull = new UmrahQuotationInput(
            adults: 2,
            children: 3,
            makkahHotel: new UmrahHotelStayInput('Makkah', 2, 10000, 'double'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 0, 0, 'double'),
            visaCost: new PricingComponentInput('Visa', 0, 'fixed'),
            transportCost: new PricingComponentInput('Transport', 0, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 0, 'fixed'),
            extras: [],
            markupType: 'fixed',
            markupValue: 0,
            childOccupancyFactor: 1.0
        );

        $inputHalf = new UmrahQuotationInput(
            adults: 2,
            children: 3,
            makkahHotel: new UmrahHotelStayInput('Makkah', 2, 10000, 'double'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 0, 0, 'double'),
            visaCost: new PricingComponentInput('Visa', 0, 'fixed'),
            transportCost: new PricingComponentInput('Transport', 0, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 0, 'fixed'),
            extras: [],
            markupType: 'fixed',
            markupValue: 0,
            childOccupancyFactor: 0.5
        );

        $full = $calculator->calculate($inputFull);
        $half = $calculator->calculate($inputHalf);

        $this->assertSame(60000.0, $full->makkahHotelTotal);
        $this->assertSame(40000.0, $half->makkahHotelTotal);
    }

    public function test_optional_extras_fixed_and_per_person_combine(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 2,
            children: 1,
            makkahHotel: new UmrahHotelStayInput('Makkah', 0, 0, 'double'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 0, 0, 'double'),
            visaCost: new PricingComponentInput('Visa', 0, 'fixed'),
            transportCost: new PricingComponentInput('Transport', 0, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 0, 'fixed'),
            extras: [
                new PricingComponentInput('Meals', 12000, 'fixed'),
                new PricingComponentInput('SIM', 2000, 'per_person'),
            ],
            markupType: 'fixed',
            markupValue: 0
        );

        $result = $calculator->calculate($input);

        $this->assertSame(18000.0, $result->extrasTotal);
        $this->assertSame(18000.0, $result->grandTotal);
    }

    public function test_percentage_markup_of_zero_leaves_grand_total_equal_to_subtotal(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 1,
            children: 0,
            makkahHotel: new UmrahHotelStayInput('Makkah', 1, 5000, 'single'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 1, 4000, 'single'),
            visaCost: new PricingComponentInput('Visa', 1000, 'per_person'),
            transportCost: new PricingComponentInput('Transport', 500, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 0, 'fixed'),
            extras: [],
            markupType: 'percentage',
            markupValue: 0
        );

        $result = $calculator->calculate($input);

        $this->assertSame(0.0, $result->markupAmount);
        $this->assertSame($result->subTotal, $result->grandTotal);
        $this->assertSame(10500.0, $result->grandTotal);
    }

    public function test_traveler_counts_include_children_for_per_person_pricing(): void
    {
        $calculator = $this->calculator();

        $input = new UmrahQuotationInput(
            adults: 1,
            children: 2,
            makkahHotel: new UmrahHotelStayInput('Makkah', 0, 0, 'triple'),
            madinahHotel: new UmrahHotelStayInput('Madinah', 0, 0, 'triple'),
            visaCost: new PricingComponentInput('Visa', 8000, 'per_person'),
            transportCost: new PricingComponentInput('Transport', 0, 'fixed'),
            flightCost: new PricingComponentInput('Flight', 5000, 'per_person'),
            extras: [],
            markupType: 'fixed',
            markupValue: 0
        );

        $result = $calculator->calculate($input);

        $this->assertSame(24000.0, $result->visaTotal);
        $this->assertSame(15000.0, $result->flightTotal);
        $this->assertSame(39000.0, $result->grandTotal);
    }

    private function calculator(): UmrahQuotationCalculator
    {
        return new UmrahQuotationCalculator(
            new RoomPricingService(),
            new ComponentPricingService()
        );
    }
}
