# PolicyFlow: Implementation Summary

**Product:** TrippersShield / PolicyFlow  
**Module:** Travel Insurance Policy Intake, Issuance & Reconciliation Engine  
**Author:** Ojasvi Mishra (`ojasvimishra9792@gmail.com`)  
**Architecture:** Modular Laravel Monolith + React/TypeScript Operational Portal  

---

## 1. Problem Statement & Purpose

When partner travel agents sell travel insurance policies, external insurer APIs often experience transient timeouts, network partitions, or delayed asynchronous callbacks. In a naive system, retrying unconfirmed requests creates **duplicate policies**—resulting in double-charging customers, inflated partner liabilities, and severe regulatory non-compliance.

**PolicyFlow solves this problem through defensive distributed systems engineering:**
1. **Compound Intake Idempotency**: Guarantees that identical requests `(agent_id, client_request_id)` resolve to the existing canonical record with an immutable pricing snapshot.
2. **Deterministic Decimal Arithmetic**: Eliminates floating-point discrepancies via `bcmath`.
3. **Two-Phase Lock Isolation**: Secures sequential attempt allocation without holding database locks during external network latency.
4. **Defensive Timeout Recovery**: Unconfirmed provider timeouts transition policies into `PENDING_CONFIRMATION` instead of blind reissuing.
5. **Webhook Deduplication & State Integrity**: Enforces `UNIQUE(provider, event_id)` and prevents late-arriving failure callbacks from regressing terminal `ISSUED` policies.
6. **Automated Background Reconciliation**: Resolves ambiguous policies against provider status APIs in an idempotent manner.

---

## 2. System Architecture & Tech Stack

```
[ Travel Agent / Portal Browser ]
                │
                ▼ (HTTP: 80 / 443)
┌────────────────────────────────────────────────────────┐
│ Edge Nginx Reverse Proxy (Nginx Alpine)                │
│ - Delivers compiled React 18 SPA static bundle         │
│ - Proxies /api/* & /up to PHP-FPM                      │
└───────────────────────┬────────────────────────────────┘
                        │
                        ▼ (FastCGI: 9000)
┌────────────────────────────────────────────────────────┐
│ Laravel 11 Backend Core (PHP 8.2-FPM Alpine)           │
│ - Strict typing, FormRequests, Eloquent ORM            │
│ - bcmath decimal-precision calculation engine          │
└───────────────┬────────────────────────┬───────────────┘
                │                        │
                ▼                        ▼
┌──────────────────────────────┐ ┌──────────────────────┐
│ PostgreSQL 16 Alpine         │ │ Redis 7 Alpine       │
│ - ACID transactions          │ │ - Queues & cache     │
│ - Row locking (FOR UPDATE)   │ │ - Distributed state  │
│ - Unique compound indexes    │ └──────────────────────┘
└──────────────────────────────┘
```

---

## 3. Core Domain Components

### 3.1 Policy Intake (`PolicyIntakeService`)
- Validates 9 required fields (`client_request_id`, `agent_id`, `customer_name`, `date_of_birth`, `destination`, `trip_start`, `trip_end`, `plan_code`, `sum_insured`).
- Checks database for existing `(agent_id, client_request_id)`:
  - If present, returns HTTP 200 with original canonical policy and pricing snapshot (idempotent replay).
  - If new, calculates deterministic premium, stores immutable snapshot, and returns HTTP 201 Created.

### 3.2 Pricing Calculation Engine (`PremiumCalculationService`)
- **Formula:**  
  $$\text{Final Premium} = \text{Base Daily Rate} \times \text{Travel Days} \times \text{Age Multiplier} \times \text{Destination Multiplier} \times \text{Coverage Multiplier}$$
- **Multipliers:**
  - Base Daily Rate: BASIC = ₹12.00, PLUS = ₹20.00, PREMIUM = ₹30.00.
  - Travel Days: Inclusive days: `(trip_end - trip_start) + 1`.
  - Age at Trip Start: 0–17 = 1.10; 18–60 = 1.00; 61–70 = 1.50; 71+ = 2.00.
  - Destination: DOMESTIC = 1.00; INTERNATIONAL = 1.80.
  - Coverage: ₹100k = 1.00; ₹300k = 1.25; ₹500k = 1.50.
- **Reference Case:** PLUS plan, 5 days, INTERNATIONAL, age 28, ₹300,000 coverage:
  $$20.00 \times 5 \times 1.00 \times 1.80 \times 1.25 = \mathbf{₹225.00}$$

### 3.3 Issuance Orchestrator (`IssuanceOrchestrator`)
- Employs a **Two-Phase Lock Isolation** model:
  1. **Phase 1 (Lock Acquisition):** Acquires `PolicyRequest::lockForUpdate()`, validates transition, increments sequential attempt number, and sets state to `SUBMITTED`. Releases lock immediately.
  2. **Provider Call:** Executes external HTTP call outside transaction, preventing database connection pool starvation.
  3. **Phase 2 (Finalization):** Briefly reacquires lock, logs response in `issuance_attempts`, and updates state to `ISSUED`, `FAILED`, or `PENDING_CONFIRMATION`.

### 3.4 State Machine (`PolicyStateTransitionService`)
- Enforces strict transition matrix across 7 formal states:
  - `RECEIVED` -> `VALIDATED`, `FAILED`
  - `VALIDATED` -> `SUBMITTED`, `FAILED`
  - `SUBMITTED` -> `ISSUED`, `PENDING_CONFIRMATION`, `NEEDS_RECONCILIATION`, `FAILED`
  - `PENDING_CONFIRMATION` -> `ISSUED`, `NEEDS_RECONCILIATION`, `FAILED`
  - `NEEDS_RECONCILIATION` -> `ISSUED`, `SUBMITTED`, `FAILED`
  - `FAILED` -> `SUBMITTED` (bounded retry)
  - `ISSUED` -> `[]` (Terminal immutable state; zero outbound transitions allowed).

### 3.5 Provider Webhook Processing (`ProviderCallbackService`)
- Ingests incoming callbacks into `provider_events`.
- Enforces `UNIQUE(provider, event_id)` at the database schema level.
- Disallows late-arriving failure callbacks from regressing a policy that was already issued.

### 3.6 Automated Reconciliation Engine (`ReconciliationService`)
- Command: `php artisan policyflow:reconcile --limit=100` (scheduled `*/5 * * * *` via `routes/console.php`).
- Queries insurer status endpoint for unconfirmed requests:
  - If provider confirms policy was issued: transitions to `ISSUED`, stores policy number.
  - If provider confirms no policy exists: marks `FAILED`, safe for operator retry.
  - Repeated reconciliation passes are 100% idempotent.

---

## 4. Frontend Operations Portal

Built with React 18, TypeScript 5, Vite, and Lucide React icons:
1. **Overview Dashboard (`/`)**: Displays real-time metrics (Total, Issued, Pending Confirmation, Failed, Total Issued Premium) with zero fake data.
2. **Policy Request List (`/policies`)**: Filterable, paginated table of intake requests.
3. **New Policy Intake (`/policies/new`)**: Live dynamic premium calculation preview (matching reference ₹225.00), input validation, and double-click submission prevention.
4. **Policy Detail View (`/policies/:id`)**: Comprehensive policy metadata, chronological state transition audit trail, and issuance attempt logs.
5. **Operations & Reconciliation (`/operations`)**: Operational view allowing one-click batch reconciliation with live feedback.

---

## 5. Automated Testing Strategy

1. **Unit Testing**: Tests mathematical edge cases, inclusive travel days, birthday boundaries, and all state machine transitions.
2. **Feature Integration Testing**: Verifies API endpoints, intake idempotency, mock insurer failure scenarios, and webhook deduplication.
3. **Concurrency Testing**: Multi-threaded Python suite testing 10 concurrent intake threads and 4 concurrent submission workers under real database locking.
4. **Frontend Testing**: Vitest suite confirming exact pricing calculation parity and API client contracts.
5. **CI/CD Quality Gate**: GitHub Actions workflow testing PHP 8.2 and PHP 8.3 matrices with live PostgreSQL and Redis services on every commit.
