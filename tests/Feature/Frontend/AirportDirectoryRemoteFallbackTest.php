<?php

namespace Tests\Feature\Frontend;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AirportDirectoryRemoteFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('travel.airports.directory.v4.local');
        Cache::forget('travel.airports.directory.v4.remote');
    }

    public function test_airport_search_uses_remote_fallback_for_mel_query(): void
    {
        config()->set('services.airport_directory.remote_search_enabled', true);
        config()->set('services.airport_directory.remote_base_url', 'https://example.test/airports.json');

        Http::fake([
            'https://example.test/airports.json' => Http::response([
                'YMML' => [
                    'iata' => 'MEL',
                    'name' => 'Melbourne International Airport',
                    'city' => 'Melbourne',
                    'country' => 'Australia',
                ],
                'YPPH' => [
                    'iata' => 'PER',
                    'name' => 'Perth Airport',
                    'city' => 'Perth',
                    'country' => 'Australia',
                ],
            ], 200),
        ]);

        $response = $this->getJson(route('frontend.airports.search', [
            'q' => 'mel',
            'limit' => 10,
        ]));

        $response->assertOk();
        $iataCodes = collect($response->json('data'))->pluck('iata')->all();
        $this->assertContains('MEL', $iataCodes);
    }

    public function test_global_index_endpoint_includes_remote_airports_for_client_prefetch(): void
    {
        config()->set('services.airport_directory.remote_search_enabled', true);
        config()->set('services.airport_directory.remote_base_url', 'https://example.test/airports.json');

        Http::fake([
            'https://example.test/airports.json' => Http::response([
                'YMML' => [
                    'iata' => 'MEL',
                    'name' => 'Melbourne International Airport',
                    'city' => 'Melbourne',
                    'country' => 'Australia',
                ],
            ], 200),
        ]);

        $response = $this->getJson(route('frontend.airports.global-index'));

        $response->assertOk();
        $response->assertJsonStructure(['v', 'r']);
        $rows = collect($response->json('r'))->filter(fn ($row): bool => is_array($row) && ($row[0] ?? null) === 'MEL');
        $this->assertTrue($rows->isNotEmpty());
    }
}

