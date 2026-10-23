# Queue & Reliability Architecture

## 1. Overview
PolicyFlow integrates Redis 7+ and Laravel Queues to offload external insurer HTTP requests, webhook ingestion, and background reconciliation. The queuing architecture is intentionally designed around **at-least-once delivery**, acknowledging that distributed queues can redeliver jobs upon network interruptions or worker timeouts.

---

## 2. Queue Allocation & Worker Topology

| Queue Name | Purpose | Concurrency / Worker Priority | Timeout | Max Tries | Backoff (sec) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `issuance` | Initial and retried policy issuance requests to Mock Insurer | Priority 1 (High) | 60s | 3 | [10, 30, 60] |
| `callbacks` | Async webhook events delivered by external insurers | Priority 2 (Medium) | 30s | 5 | [5, 15, 30] |
| `reconciliation` | Scheduled and on-demand reconciliation of stale requests | Priority 3 (Batch) | 120s | 2 | [60, 180] |

### Worker Startup Command
```bash
# Standard local development worker
php artisan queue:work redis --queue=issuance,callbacks,reconciliation,default --sleep=3 --tries=3 --timeout=60
```

---

## 3. Asynchronous Job Inventory

### A. `SubmitPolicyForIssuance`
- **Trigger**: Dispatched upon policy validation or explicit `POST /api/policy-requests/{id}/submit`.
- **Payload**: `policy_request_id`, `requested_scenario` (optional test override).
- **Behavior**:
  1. Checks if policy is already `ISSUED` or `IN_FLIGHT`. If `ISSUED`, terminates immediately (idempotent no-op).
  2. Acquires row lock: `PolicyRequest::where('id', $id)->lockForUpdate()->first()`.
  3. Records `IssuanceAttempt`.
  4. Calls Mock Insurer adapter.
  5. Upon success: updates `policy_requests.status = 'ISSUED'`, records state history.
  6. Upon timeout/connection failure: updates status to `PENDING_CONFIRMATION` (never reissues blindly).
  7. Upon 5xx retriable error: releases job back to Redis with exponential backoff if attempt count < 3.

### B. `ReconcilePendingPolicy`
- **Trigger**: Dispatched periodically or after a timeout attempt.
- **Payload**: `policy_request_id`.
- **Behavior**:
  1. Queries Mock Insurer's status endpoint (`/api/mock-insurer/status/{client_request_id}`).
  2. If insurer confirms policy was created: transitions local status to `ISSUED`.
  3. If insurer confirms no policy exists: transitions to `NEEDS_RECONCILIATION` or re-enables retry if safe.

### C. `ProcessProviderCallback`
- **Trigger**: Dispatched by `MockInsurerCallbackController` upon receiving a webhook.
- **Payload**: Raw callback body and headers.
- **Behavior**:
  1. Inserts into `provider_events` with unique constraint on `(provider, event_id)`.
  2. If duplicate event, drops quietly.
  3. Updates associated `PolicyRequest` status if valid state transition.

---

## 4. Reliability Invariants & Edge Case Protections

### 1. Safe Dispatch After Commit (`afterCommit: true`)
A common race condition occurs when a job is dispatched before the enclosing database transaction commits; the background worker pops the job and cannot find the record in the database.
```php
// Enforced in all PolicyFlow jobs:
public bool $afterCommit = true;
```

### 2. At-Least-Once Delivery & Duplicate Execution Guard
Every job checks the current database state inside a transaction before executing business side-effects:
```php
DB::transaction(function () use ($policyId) {
    $policy = PolicyRequest::where('id', $policyId)->lockForUpdate()->first();
    if ($policy->status === 'ISSUED') {
        Log::info("Policy {$policyId} already issued. Skipping duplicate job execution.");
        return;
    }
    // Proceed safely...
});
```

### 3. Distributed Lock vs Durable DB Constraints
Redis locks (`Cache::lock('policy_issue_' . $id, 10)`) are used **only** as a lightweight performance filter to throttle rapid simultaneous requests. They **never replace** database unique constraints `(agent_id, client_request_id)` or `lockForUpdate()`, because Redis keys can expire prematurely during unexpected worker pauses.

### 4. Failed Job Dead-Letter Strategy
When a job exhausts all retries:
1. Laravel captures it into the `failed_jobs` table.
2. The `failed()` lifecycle hook of `SubmitPolicyForIssuance` transitions the policy to `NEEDS_RECONCILIATION`.
3. The original accepted premium snapshot is never modified or lost.
4. The system alerts operations and marks the record for audit.
