# DB Schema

**Summary index (by domain):** `docs/15-db-schema-summary.md` — use that for onboarding and integration planning; this file stays a short pointer.

Initial schema is Laravel default tables:

- users
- cache
- jobs
- failed_jobs
- job_batches
- cache_locks

Extend schema per module using dedicated migration files.

**Integration observability (supplier GDS):** `integration_connections`, `integration_credentials`, `integration_tokens`, `integration_request_logs`, `integration_response_logs`, `integration_events`, `integration_logs` (orchestration audit), `supplier_search_sessions`, `supplier_offer_snapshots`, `supplier_booking_snapshots` — see `docs/11-integration-observability.md`.
