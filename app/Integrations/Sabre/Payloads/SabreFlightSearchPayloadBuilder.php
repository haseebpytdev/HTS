<?php

namespace App\Integrations\Sabre\Payloads;

use App\Data\Integrations\ApiErrorData;
use App\Data\Integrations\FlightSearchRequestData;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;

/**
 * Build Sabre BFM v5 OTA_AirLowFareSearchRQ payload.
 */
final class SabreFlightSearchPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function forSearch(FlightSearchRequestData $request, ?string $returnDate = null): array
    {
        $origin = strtoupper(trim($request->origin));
        $destination = strtoupper(trim($request->destination));
        $departureDate = trim($request->departureDate);
        $cabin = $request->cabinClass !== null ? strtoupper(trim($request->cabinClass)) : null;

        $this->assertIataCode($origin, 'origin');
        $this->assertIataCode($destination, 'destination');
        $this->assertIsoDate($departureDate, 'departure_date');
        $this->assertPassengerCounts($request->adults, $request->children, $request->infants);
        $this->assertCabinClass($cabin);

        $legs = [[
            'RPH' => '1',
            'DepartureDateTime' => $departureDate.'T00:00:00',
            'OriginLocation' => ['LocationCode' => $origin],
            'DestinationLocation' => ['LocationCode' => $destination],
        ]];

        if ($returnDate !== null && trim($returnDate) !== '') {
            $returnDate = trim($returnDate);
            $this->assertIsoDate($returnDate, 'return_date');
            if ($returnDate < $departureDate) {
                throw $this->invalidPayload('return_date must be after or equal to departure_date.');
            }

            $legs[] = [
                'RPH' => '2',
                'DepartureDateTime' => $returnDate.'T00:00:00',
                'OriginLocation' => ['LocationCode' => $destination],
                'DestinationLocation' => ['LocationCode' => $origin],
            ];
        }

        $passengers = [
            ['Code' => 'ADT', 'Quantity' => $request->adults],
        ];
        if ($request->children > 0) {
            $passengers[] = ['Code' => 'CNN', 'Quantity' => $request->children];
        }
        if ($request->infants > 0) {
            $passengers[] = ['Code' => 'INF', 'Quantity' => $request->infants];
        }

        $requestorId = trim((string) config('sabre.bfm.requestor_id', 'DEVCENTER'));
        $companyCode = trim((string) config('sabre.bfm.company_code', 'TN'));
        $source = [
            'RequestorID' => [
                'Type' => '1',
                'ID' => $requestorId !== '' ? $requestorId : 'DEVCENTER',
            ],
        ];
        if ($companyCode !== '') {
            $source['RequestorID']['CompanyName'] = [
                'Code' => strtoupper($companyCode),
            ];
        }
        $pseudoCityCode = $this->resolvePseudoCityCode();
        if ($pseudoCityCode !== '') {
            $source['PseudoCityCode'] = strtoupper($pseudoCityCode);
        }

        $payload = [
            'OTA_AirLowFareSearchRQ' => [
                'Version' => '5',
                'POS' => [
                    'Source' => [$source],
                ],
                'OriginDestinationInformation' => $legs,
                'TravelerInfoSummary' => [
                    'SeatsRequested' => [max(1, $request->adults + $request->children)],
                    'AirTravelerAvail' => [[
                        'PassengerTypeQuantity' => $passengers,
                    ]],
                ],
                'TPA_Extensions' => [
                    'IntelliSellTransaction' => [
                        'RequestType' => [
                            'Name' => (string) config('sabre.bfm.request_type', '50ITINS'),
                        ],
                    ],
                ],
            ],
        ];

        if ($cabin !== null && $cabin !== '') {
            $payload['OTA_AirLowFareSearchRQ']['TravelPreferences'] = [
                'CabinPref' => [[
                    'Cabin' => $this->normalizeCabinCode($cabin),
                ]],
            ];
        }

        return $payload;
    }

    private function assertIataCode(string $value, string $field): void
    {
        if (! preg_match('/^[A-Z]{3}$/', $value)) {
            throw $this->invalidPayload(sprintf('%s must be a valid 3-letter IATA code.', $field));
        }
    }

    private function assertIsoDate(string $value, string $field): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw $this->invalidPayload(sprintf('%s must use Y-m-d format.', $field));
        }
    }

    private function assertPassengerCounts(int $adults, int $children, int $infants): void
    {
        if ($adults < 1 || $adults > 9) {
            throw $this->invalidPayload('adults must be between 1 and 9.');
        }
        if ($children < 0 || $children > 9) {
            throw $this->invalidPayload('children must be between 0 and 9.');
        }
        if ($infants < 0 || $infants > 9) {
            throw $this->invalidPayload('infants must be between 0 and 9.');
        }
        if ($infants > $adults) {
            throw $this->invalidPayload('infants cannot exceed adults.');
        }
    }

    private function assertCabinClass(?string $cabin): void
    {
        if ($cabin === null || $cabin === '') {
            return;
        }

        if (! in_array($cabin, ['Y', 'S', 'C', 'J', 'F', 'ECONOMY', 'PREMIUM_ECONOMY', 'BUSINESS', 'FIRST'], true)) {
            throw $this->invalidPayload('cabin_class is invalid for Sabre BFM.');
        }
    }

    private function normalizeCabinCode(string $cabin): string
    {
        return match ($cabin) {
            'ECONOMY' => 'Y',
            'PREMIUM_ECONOMY' => 'S',
            'BUSINESS' => 'C',
            'FIRST' => 'F',
            default => $cabin,
        };
    }

    private function resolvePseudoCityCode(): string
    {
        $configured = trim((string) config('sabre.bfm.pseudo_city_code', ''));
        if ($configured !== '') {
            return $configured;
        }

        if (app()->environment(['local', 'testing'])
            || strtolower((string) config('integrations.credential_environment', 'production')) === 'test'
        ) {
            return 'DEVCENTER';
        }

        return '';
    }

    private function invalidPayload(string $message): SupplierIntegrationException
    {
        return new SupplierIntegrationException(
            message: $message,
            supplierCode: 'SABRE_BFM_PAYLOAD_INVALID',
            normalizedCode: 'supplier_request_invalid',
            apiError: new ApiErrorData(
                code: 'supplier_request_invalid',
                message: $message,
                supplierCode: 'SABRE_BFM_PAYLOAD_INVALID',
                httpStatus: 422,
            )
        );
    }
}
