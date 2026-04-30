# Integration observability (raw archive + normalized snapshots)

Phase **11** supplier work benefits from **two parallel persistence tracks**:

1. **Raw wire archive** — full fidelity when vendor JSON shapes drift or regressions need replay.
2. **Normalized business snapshots** — stable queries for “what we showed the user” and reconciliation.

Application code should still use **`App\Data\Integrations\*` DTOs** in memory; persistence stores **arrays** (JSON columns) produced from those DTOs or from mappers. When saving normalized offers or prices, prefer **`json_encode($dto)`** (DTOs implement `JsonSerializable`) so **`NormalizedPayloadMetadata`** stamps are preserved — see `docs/10-supplier-integration-layer.md` (JSON schema versioning).

## Tables

| Table | Role |
|-------|------|
| `integration_connections` | Named supplier endpoints (provider, environment, `base_url`, non-secret `config`). |
| `integration_credentials` | Per-connection secrets (`key_name` + Laravel **encrypted** `secret`). |
| `integration_tokens` | Cached OAuth/access tokens (**encrypted** `token` / `refresh_token`). |
| `integration_request_logs` | Raw request: headers, body, `provider`, `operation`, `environment`, `correlation_id`, `trace_id`, optional `user_id`. |
| `integration_response_logs` | One row per request: `status_code`, headers, body, `latency_ms`, `error_category`. |
| `integration_events` | Timeline events (`event_type`, `correlation_id`, optional aggregate ids, `payload`). |
| `supplier_search_sessions` | Normalized search lifecycle + `internal_request_snapshot` + `search_results_summary`. |
| `supplier_offer_snapshots` | Per-offer normalized payload + optional `selected_fare_summary`. |
| `supplier_booking_snapshots` | Booking summary: `internal_status`, `normalized_totals`, `travelers_json`, `booking_summary`, optional link to `integration_request_log_id`. |

## Writing raw exchanges

Use **`App\Actions\Integrations\RecordIntegrationRawExchangeAction`** with **`IntegrationRawExchangeData`** so request and response rows stay paired and share `correlation_id`.

Adapters should generate a **correlation ID** (e.g. `Str::uuid()`) per outbound call and pass it through to the response logger.

## Oversized JSON

Bodies use **`json` columns** for simplicity. If a vendor returns payloads beyond DB limits, archive a **truncation + object storage pointer** in `request_body` / `response_body` and document the blob location (future enhancement).

## Deployment

Apply migration:

`database/migrations/2026_04_09_120000_create_integration_observability_tables.php`

If full `php artisan migrate` fails on an out-of-sync database, use:

```bash
php artisan migrate --path=database/migrations/2026_04_09_120000_create_integration_observability_tables.php --no-interaction --force
```

Ensure **`APP_KEY`** is set so **encrypted** casts on credentials and tokens work.

## Related

- Supplier contracts and normalized DTOs: `docs/10-supplier-integration-layer.md`
