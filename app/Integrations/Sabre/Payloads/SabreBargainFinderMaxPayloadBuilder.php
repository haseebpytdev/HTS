<?php

namespace App\Integrations\Sabre\Payloads;

use App\Data\Integrations\FlightSearchRequestData;

final class SabreBargainFinderMaxPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function forSearch(FlightSearchRequestData $request): array
    {
        $passengers = array_values(array_filter([
            ['Code' => 'ADT', 'Quantity' => max(1, $request->adults)],
            $request->children > 0 ? ['Code' => 'CNN', 'Quantity' => $request->children] : null,
            $request->infants > 0 ? ['Code' => 'INF', 'Quantity' => $request->infants] : null,
        ]));

        $payload = [
            'OTA_AirLowFareSearchRQ' => [
                'Version' => '5',
                'OriginDestinationInformation' => [[
                    'RPH' => '1',
                    'DepartureDateTime' => $request->departureDate.'T00:00:00',
                    'OriginLocation' => ['LocationCode' => strtoupper($request->origin)],
                    'DestinationLocation' => ['LocationCode' => strtoupper($request->destination)],
                ]],
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

        if ($request->cabinClass !== null && trim($request->cabinClass) !== '') {
            $payload['OTA_AirLowFareSearchRQ']['TravelPreferences'] = [
                'CabinPref' => [[
                    'Cabin' => strtoupper($request->cabinClass),
                    'PreferLevel' => 'Preferred',
                ]],
            ];
        }

        return $payload;
    }
}
