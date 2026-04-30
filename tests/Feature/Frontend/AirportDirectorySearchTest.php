<?php

namespace Tests\Feature\Frontend;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AirportDirectorySearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('travel.airports.directory.v4.local');
    }

    public function test_minified_airport_index_files_exist_and_version_matches_index(): void
    {
        $versionPath = public_path('data/airports.version.json');
        $indexPath = public_path('data/airports.index.min.json');
        $this->assertFileExists($versionPath);
        $this->assertFileExists($indexPath);

        $version = json_decode((string) file_get_contents($versionPath), true);
        $index = json_decode((string) file_get_contents($indexPath), true);
        $this->assertIsArray($version);
        $this->assertIsArray($index);
        $this->assertArrayHasKey('v', $version);
        $this->assertArrayHasKey('v', $index);
        $this->assertArrayHasKey('r', $index);
        $this->assertSame(16, strlen((string) $version['v']));
        $this->assertSame($version['v'], $index['v']);
        $this->assertGreaterThan(0, count($index['r']));
        $this->assertCount(4, $index['r'][0]);
    }

    public function test_airport_search_returns_lahore_for_lhe_query(): void
    {
        $response = $this->getJson(route('frontend.airports.search', [
            'q' => 'LHE',
            'limit' => 10,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.iata', 'LHE');
        $response->assertJsonPath('data.0.city', 'Lahore');
    }

    public function test_airport_search_matches_from_first_character(): void
    {
        $response = $this->getJson(route('frontend.airports.search', [
            'q' => 'l',
            'limit' => 20,
        ]));

        $response->assertOk();
        $iataCodes = collect($response->json('data'))->pluck('iata')->all();
        $this->assertContains('LHE', $iataCodes);
        $this->assertContains('LHR', $iataCodes);
    }

    public function test_airport_search_matches_city_prefix(): void
    {
        $response = $this->getJson(route('frontend.airports.search', [
            'q' => 'laho',
            'limit' => 10,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.iata', 'LHE');
    }

    public function test_airport_search_matches_keyword_in_airport_name(): void
    {
        $response = $this->getJson(route('frontend.airports.search', [
            'q' => 'iqbal',
            'limit' => 10,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.iata', 'LHE');
    }

    public function test_airport_search_matches_country_substring(): void
    {
        $response = $this->getJson(route('frontend.airports.search', [
            'q' => 'emirates',
            'limit' => 20,
        ]));

        $response->assertOk();
        $iataCodes = collect($response->json('data'))->pluck('iata')->all();
        $this->assertContains('DXB', $iataCodes);
        $this->assertContains('AUH', $iataCodes);
    }

    public function test_airport_search_matches_two_character_substring_in_combined_fields(): void
    {
        $response = $this->getJson(route('frontend.airports.search', [
            'q' => 'stan',
            'limit' => 20,
        ]));

        $response->assertOk();
        $iataCodes = collect($response->json('data'))->pluck('iata')->all();
        $this->assertContains('LHE', $iataCodes);
        $this->assertContains('KHI', $iataCodes);
    }
}
