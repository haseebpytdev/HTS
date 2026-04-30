<?php

namespace Tests\Unit\Integrations;

use App\Data\Integrations\FlightSearchRequestData;
use App\Integrations\Sabre\Payloads\SabreFlightSearchPayloadBuilder;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use Tests\TestCase;

class SabreBfmPayloadBuilderTest extends TestCase
{
    public function test_builds_exact_bfm_v5_payload_for_lhr_jfk_one_adult_economy(): void
    {
        config([
            'sabre.bfm.request_type' => '50ITINS',
            'sabre.bfm.requestor_id' => 'DEVCENTER',
            'sabre.bfm.pseudo_city_code' => 'ABCD',
            'sabre.bfm.company_code' => 'TN',
        ]);

        $request = new FlightSearchRequestData(
            origin: 'LHR',
            destination: 'JFK',
            departureDate: '2026-06-01',
            adults: 1,
            children: 0,
            infants: 0,
            cabinClass: 'economy',
        );

        $payload = (new SabreFlightSearchPayloadBuilder)->forSearch($request);

        $expected = [
            'OTA_AirLowFareSearchRQ' => [
                'Version' => '5',
                'POS' => [
                    'Source' => [[
                        'RequestorID' => [
                            'Type' => '1',
                            'ID' => 'DEVCENTER',
                            'CompanyName' => [
                                'Code' => 'TN',
                            ],
                        ],
                        'PseudoCityCode' => 'ABCD',
                    ]],
                ],
                'OriginDestinationInformation' => [[
                    'RPH' => '1',
                    'DepartureDateTime' => '2026-06-01T00:00:00',
                    'OriginLocation' => ['LocationCode' => 'LHR'],
                    'DestinationLocation' => ['LocationCode' => 'JFK'],
                ]],
                'TravelerInfoSummary' => [
                    'SeatsRequested' => [1],
                    'AirTravelerAvail' => [[
                        'PassengerTypeQuantity' => [
                            ['Code' => 'ADT', 'Quantity' => 1],
                        ],
                    ]],
                ],
                'TPA_Extensions' => [
                    'IntelliSellTransaction' => [
                        'RequestType' => [
                            'Name' => '50ITINS',
                        ],
                    ],
                ],
                'TravelPreferences' => [
                    'CabinPref' => [[
                        'Cabin' => 'Y',
                    ]],
                ],
            ],
        ];

        $this->assertSame($expected, $payload);
    }

    public function test_uses_explicit_pseudo_city_code_when_configured(): void
    {
        config([
            'sabre.bfm.requestor_id' => 'DEVCENTER',
            'sabre.bfm.pseudo_city_code' => 'XYZ1',
            'integrations.credential_environment' => 'production',
        ]);

        $request = new FlightSearchRequestData(
            origin: 'LHR',
            destination: 'JFK',
            departureDate: '2026-06-01',
            adults: 1,
            children: 0,
            infants: 0,
            cabinClass: 'economy',
        );

        $payload = (new SabreFlightSearchPayloadBuilder)->forSearch($request);

        $source = $payload['OTA_AirLowFareSearchRQ']['POS']['Source'][0] ?? [];
        $this->assertSame('XYZ1', $source['PseudoCityCode'] ?? null);
    }

    public function test_defaults_pseudo_city_code_to_devcenter_for_sandbox_runtime(): void
    {
        config([
            'sabre.bfm.requestor_id' => 'DEVCENTER',
            'sabre.bfm.pseudo_city_code' => '',
            'integrations.credential_environment' => 'test',
        ]);

        $request = new FlightSearchRequestData(
            origin: 'LHR',
            destination: 'JFK',
            departureDate: '2026-06-01',
            adults: 1,
            children: 0,
            infants: 0,
            cabinClass: 'economy',
        );

        $payload = (new SabreFlightSearchPayloadBuilder)->forSearch($request);

        $source = $payload['OTA_AirLowFareSearchRQ']['POS']['Source'][0] ?? [];
        $this->assertSame('DEVCENTER', $source['PseudoCityCode'] ?? null);
    }

    public function test_builds_roundtrip_payload_when_return_date_is_provided(): void
    {
        $request = new FlightSearchRequestData(
            origin: 'LHR',
            destination: 'JFK',
            departureDate: '2026-06-01',
            adults: 1,
            children: 0,
            infants: 0,
            cabinClass: 'economy',
        );

        $payload = (new SabreFlightSearchPayloadBuilder)->forSearch($request, '2026-06-15');

        $this->assertCount(2, $payload['OTA_AirLowFareSearchRQ']['OriginDestinationInformation']);
        $this->assertSame('JFK', $payload['OTA_AirLowFareSearchRQ']['OriginDestinationInformation'][1]['OriginLocation']['LocationCode']);
        $this->assertSame('LHR', $payload['OTA_AirLowFareSearchRQ']['OriginDestinationInformation'][1]['DestinationLocation']['LocationCode']);
    }

    public function test_rejects_invalid_payload_before_http_call(): void
    {
        $request = new FlightSearchRequestData(
            origin: 'LH',
            destination: 'JFK',
            departureDate: '2026-06-01',
            adults: 1,
            children: 0,
            infants: 0,
            cabinClass: 'economy',
        );

        $this->expectException(SupplierIntegrationException::class);
        $this->expectExceptionMessage('origin must be a valid 3-letter IATA code.');

        (new SabreFlightSearchPayloadBuilder)->forSearch($request);
    }
}
