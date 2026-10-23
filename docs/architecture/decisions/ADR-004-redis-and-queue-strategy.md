# ADR-004: Redis & Queue Strategy — Asynchronous Issuance & Reconciliation

## Status
Accepted

## Context
External insurer interactions are inherently slow and prone to unpredictable network delays. Processing insurer calls synchronously inside the HTTP intake request blocks agent clients and exhausts web server worker threads. Furthermore, the user explicitly requested Redis and queue architecture as part of the system foundation.

## Decision
We select **Redis 7+** as the caching and queue backend, orchestrated via **Laravel Queues**.
- Synchronous intake (`POST /api/policy-requests`) validates and persists the request in the database and returns HTTP 201 immediately with status `VALIDATED`.
- Policy issuance to the mock insurer is dispatched as an asynchronous queue job (`SubmitPolicyForIssuance`) or triggered via on-demand submit endpoint.
- Dedicated queues (`issuance`, `callbacks`, `reconciliation`) prevent low-priority background reconciliation tasks from delaying real-time issuance requests.
- Test suites will configure `QUEUE_CONNECTION=sync` for deterministic synchronous test assertions, while Docker and local development run `QUEUE_CONNECTION=redis`.

## Alternatives Considered
1. **Database Queue Driver (`QUEUE_CONNECTION=database`)**:
   - High row-lock contention on the `jobs` table during high concurrency. Redis provides superior in-memory throughput and atomicity.
2. **Synchronous-Only Execution (`sync`)**:
   - Acceptable for basic assignments, but fails to demonstrate production-grade fault isolation and retry backoff.

## Consequences
- **Positive**: High throughput, fault isolation, automated retry with exponential backoff, dead-letter tracking in `failed_jobs`.
- **Negative**: Requires running a Redis container and an active `queue:work` process in production and local development.
