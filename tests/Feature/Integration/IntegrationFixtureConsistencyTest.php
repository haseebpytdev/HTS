<?php

namespace Tests\Feature\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationFixtureConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_provider_has_required_fixture_set_with_valid_json(): void
    {
        $providers = ['travelport', 'sabre', 'amadeus'];
        $required = [
            'flight_search_success',
            'flight_pricing_success',
            'booking_success',
            'auth_error',
            'validation_error',
            'fare_expired',
            'rate_limit',
            'timeout',
        ];

        foreach ($providers as $provider) {
            foreach ($required as $scenario) {
                $path = base_path("tests/Fixtures/integrations/{$provider}/{$scenario}.json");
                $this->assertFileExists($path, "Missing fixture: {$provider}/{$scenario}.json");
                $decoded = json_decode((string) file_get_contents($path), true);
                $this->assertIsArray($decoded, "Fixture is invalid JSON: {$provider}/{$scenario}.json");
            }
        }
    }

    public function test_error_fixtures_follow_expected_error_envelope_shape(): void
    {
        $providers = ['travelport', 'sabre', 'amadeus'];
        $errorScenarios = ['auth_error', 'validation_error', 'fare_expired', 'rate_limit', 'timeout'];

        foreach ($providers as $provider) {
            foreach ($errorScenarios as $scenario) {
                $path = base_path("tests/Fixtures/integrations/{$provider}/{$scenario}.json");
                /** @var array<string, mixed> $payload */
                $payload = json_decode((string) file_get_contents($path), true);

                $this->assertArrayHasKey('error', $payload, "{$provider}/{$scenario} must include error");
                $this->assertArrayHasKey('normalized_code', $payload['error']);
                $this->assertArrayHasKey('supplier_code', $payload['error']);
                $this->assertArrayHasKey('http_status', $payload['error']);
            }
        }
    }
}
