# System Overview & Architecture

## 1. Product Context
**TrippersShield / PolicyFlow** is a fault-tolerant travel insurance policy issuance and reconciliation engine. The application processes fictional travel insurance policy purchase requests submitted by travel agents. Because external insurer APIs are inherently prone to timeouts, delayed responses, and network partitions, PolicyFlow guarantees:
- **Intake Idempotency**: Zero duplicate policy requests for the same `(agent_id, client_request_id)`.
- **Deterministic Pricing**: Decimal-safe immutable premium snapshots calculated using strict business rules.
- **Issuance Resilience**: Guaranteed tracking of provider communication attempts without duplicate issuance, even when insurer responses time out or callbacks are replayed.
- **Durable Audit Trail**: Unalterable historical record of every state transition, attempt, and provider payload.

---

## 2. Architectural Paradigm: Modular Laravel Monolith
PolicyFlow is designed as a **Modular Laravel Monolith**. This paradigm provides:
1. **Low Operational Overhead**: Ideal for local execution, assessment reproducibility, and single-container deployments without distributed microservice complexity.
2. **Strict Layered Separation**: Controllers remain ultra-thin, delegating orchestration to Application Services, Domain Calculation Services, and transactional database repositories.
3. **Async Flexibility**: Redis-backed background queues for long-running provider calls and reconciliation while maintaining immediate synchronous intake guarantees.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              CLIENT / AGENT                                 │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │ HTTP / REST
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          HTTP & PRESENTATION LAYER                          │
│  ┌───────────────────────┐                    ┌──────────────────────────┐  │
│  │ PolicyRequestController│                   │ MockInsurerCallbackCtrl  │  │
│  └───────────┬───────────┘                    └─────────────┬────────────┘  │
│              │ FormRequest Validation                       │               │
└──────────────┼──────────────────────────────────────────────┼───────────────┘
               │                                              │
               ▼                                              ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         APPLICATION SERVICE LAYER                           │
│  ┌────────────────────────┐  ┌───────────────────────┐  ┌────────────────┐  │
│  │ PolicyIntakeService    │  │ IssuanceOrchestrator  │  │CallbackHandler │  │
│  └───────────┬────────────┘  └───────────┬───────────┘  └───────┬────────┘  │
│              │                           │                      │           │
│              ▼                           ▼                      ▼           │
│  ┌────────────────────────┐  ┌───────────────────────┐  ┌────────────────┐  │
│  │ PremiumCalculator      │  │ StateTransitionManager│  │Reconciliation  │  │
│  └────────────────────────┘  └───────────────────────┘  └────────────────┘  │
└──────────────┬───────────────────────────┬──────────────────────┬───────────┘
               │                           │                      │
               ▼                           ▼                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                     DOMAIN & TRANSACTION PERSISTENCE                        │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ Eloquent Models: PolicyRequest, IssuanceAttempt,                      │  │
│  │                  PolicyStateHistory, ProviderEvent                    │  │
│  │ Database Transactions (ACID) & Row-Level Locking (FOR UPDATE)         │  │
│  └───────────────────────────────────┬───────────────────────────────────┘  │
└──────────────────────────────────────┼──────────────────────────────────────┘
                                       │
                    ┌──────────────────┴──────────────────┐
                    ▼                                     ▼
┌──────────────────────────────────────┐  ┌───────────────────────────────────┐
│     PRIMARY STORE: PostgreSQL 16     │  │   CACHE & QUEUES: Redis 7+        │
│  - policy_requests                   │  │  - default queue (Async issuance) │
│  - issuance_attempts                 │  │  - reconciliation queue           │
│  - policy_state_history              │  │  - distributed locks (short-term) │
│  - provider_events                   │  │                                   │
│  - mock_insurer_policies (isolated)  │  │                                   │
└──────────────────────────────────────┘  └───────────────────────────────────┘
                    ▲                                     │
                    │ Query/Update                        ▼
┌───────────────────┴─────────────────────────────────────────────────────────┐
│                           BACKGROUND WORKERS & CLI                          │
│  - artisan queue:work redis (SubmitPolicyForIssuance, ReconcileJob)         │
│  - artisan policyflow:reconcile-stale                                       │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼ HTTP (Mock Transport)
┌─────────────────────────────────────────────────────────────────────────────┐
│                  DETERMINISTIC MOCK INSURER SUBSYSTEM                       │
│  - Route: /api/mock-insurer/issue                                           │
│  - Scenarios: SUCCESS, TEMPORARY_FAILURE, TIMEOUT_AFTER_ISSUE,              │
│               DELAYED_CALLBACK, DUPLICATE_CALLBACK                          │
│  - Isolated Provider Persistence: mock_insurer_policies                     │
│  - Status Lookup / Reconciliation Endpoint: /api/mock-insurer/status/{ref}  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Component Boundaries & Responsibilities

| Layer / Component | File Location | Responsibility |
| :--- | :--- | :--- |
| **HTTP Intake API** | `app/Http/Controllers/Api/PolicyRequestController.php` | Validates intake payload, invokes Intake Service, returns HTTP 201 (new) or 200 (existing duplicate). |
| **Intake Form Request** | `app/Http/Requests/StorePolicyRequest.php` | Enforces field types, mandatory fields, ISO dates, `trip_end >= trip_start`, supported plans and sum insured limits. |
| **Policy Intake Service** | `app/Services/PolicyIntakeService.php` | Manages atomic creation of `PolicyRequest`, checks idempotency key `(agent_id, client_request_id)`, triggers premium snapshot. |
| **Premium Calculator** | `app/Services/PremiumCalculationService.php` | Pure domain service executing deterministic pricing formula using `bcmath`. Returns immutable pricing snapshot. |
| **Issuance Orchestrator** | `app/Services/IssuanceOrchestrator.php` | Orchestrates policy submission to insurer (sync or queue), manages attempts, enforces single-flight constraints. |
| **State Transition Manager** | `app/Services/PolicyStateTransitionService.php` | Validates and persists all state transitions. Disallows regressions (e.g., `ISSUED` -> `SUBMITTED`). |
| **Mock Insurer Client** | `app/Services/MockInsurer/MockInsurerClient.php` | HTTP adapter calling mock insurer endpoint with scenario simulation headers/payloads. |
| **Mock Insurer Service** | `app/Services/MockInsurer/MockInsurerService.php` | Simulates external provider behavior; persists provider state in isolated table `mock_insurer_policies`. |
| **Callback Handler** | `app/Services/ProviderCallbackService.php` | Receives async provider callbacks, logs raw payload in `provider_events`, deduplicates event IDs, updates policy status. |
| **Reconciliation Engine**| `app/Services/ReconciliationService.php` | Identifies stale `PENDING_CONFIRMATION` or unconfirmed requests, queries provider status API, resolves to `ISSUED` or `FAILED`. |
| **Queue Jobs** | `app/Jobs/SubmitPolicyForIssuance.php`, `app/Jobs/ReconcilePendingPolicy.php` | Asynchronous worker units executing idempotent issuance and polling. |

---

## 4. Key Architectural Invariants
1. **Database as Single Source of Truth**: Cache or memory state can be lost; database transactional state must always be complete and recoverable.
2. **Never Blindly Reissue**: If an insurer submission times out or returns an ambiguous response, the system MUST NOT issue a new policy. It transitions to `PENDING_CONFIRMATION` or `NEEDS_RECONCILIATION` and inspects provider status first.
3. **Immutable Premium Snapshot**: Once calculated at intake, `base_daily_rate`, `age_multiplier`, `destination_multiplier`, `coverage_multiplier`, and `final_premium` are locked in `policy_requests`. Retries never recalculate premium.
4. **Isolated Mock Provider**: The mock insurer maintains its own storage table (`mock_insurer_policies`), completely decoupled from `policy_requests`, guaranteeing realistic black-box interaction.
