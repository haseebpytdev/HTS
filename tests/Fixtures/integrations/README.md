# Integration Fixtures Contract

This directory is the source of truth for fixture-driven supplier simulation in tests.

## Required scenarios per provider

Each provider directory (`travelport`, `sabre`, `amadeus`) must include:

- `flight_search_success.json`
- `flight_pricing_success.json`
- `booking_success.json`
- `auth_error.json`
- `validation_error.json`
- `fare_expired.json`
- `rate_limit.json`
- `timeout.json`

## Error fixture shape

Error scenarios should include:

- top-level `error`
- `error.normalized_code`
- `error.supplier_code`
- `error.http_status`

These are consumed by stub adapters and normalized envelope assertions in API tests.

## Selection rules in tests

- Global default scenario: `integrations.stub_scenario`
- Provider override: `integrations.stub_provider_scenarios[provider]`
- Pricing can also override through request `opaque_context.scenario`

Use provider overrides when testing multi-provider fallback and partial-failure behavior.
