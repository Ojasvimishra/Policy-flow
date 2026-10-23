# ADR-005: Idempotency & Reliability Approach

## Status
Accepted

## Context
In insurance policy distribution, double issuance of policies represents a severe operational and financial failure. The system must guarantee reliability across multiple failure modes:
1. **Duplicate Intake**: Rapidly repeated submissions with identical `(agent_id, client_request_id)`.
2. **Timeout After Success**: The mock insurer issues a policy, but the HTTP connection drops before PolicyFlow receives the response.
3. **Duplicate Webhook Callbacks**: The provider delivers identical events multiple times.
4. **Retry After Temporary Failure**: Failures must not alter the calculated premium or generate multiple policies.

## Decision
We enforce a multi-layered defense strategy:
1. **Intake Uniqueness**: Database-level unique constraint on `(agent_id, client_request_id)`. Concurrent duplicates result in an atomic lookup returning the existing record with HTTP 200.
2. **Defensive Timeout Handling**: When an issuance call encounters a socket timeout or 504 Gateway Timeout, the policy status is set to `PENDING_CONFIRMATION` (never `FAILED` and never blindly reissued).
3. **Reconciliation Query**: Before any retry is allowed on a pending policy, PolicyFlow queries the mock insurer's status endpoint (`/api/mock-insurer/status/{client_request_id}`). If the policy already exists at the provider, PolicyFlow updates local status to `ISSUED` without calling the issue endpoint again.
4. **Callback Deduplication**: Incoming provider callbacks are persisted in `provider_events` with a unique index on `(provider, event_id)`. Duplicate event deliveries are detected and discarded.
5. **Immutable Premium Snapshot**: Base rate, multipliers, and final premium are calculated once and stored directly in `policy_requests`. Retries reuse the snapshot and never recalculate.

## Alternatives Considered
1. **Application-Only Idempotency Checks (`if (!Policy::exists())`)**:
   - *Rejected*: Subject to race conditions under high concurrency where two requests pass the existence check simultaneously before either commits.
2. **Blind Immediate Retry on Timeout**:
   - *Rejected*: Direct cause of duplicate policy generation.

## Consequences
- **Positive**: Zero risk of duplicate policy issuance; reliable recovery from intermittent network partitions.
- **Negative**: Requires maintaining attempt history and implementing status reconciliation endpoints.
