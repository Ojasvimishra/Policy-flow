# Database Architecture & Schema Blueprint

## 1. Relational Database Strategy: PostgreSQL 16
PostgreSQL is chosen as the primary transactional database (with MySQL 8+ support as an assignment-compliant alternative).
Key PostgreSQL capabilities leveraged:
- **Strict ACID Guarantees**: Transactional isolation prevents partial writes.
- **Row-Level Locking**: `SELECT ... FOR UPDATE` eliminates race conditions during concurrent issuance attempts.
- **JSONB Native Storage**: High-performance querying and validation of provider request/response payloads without losing schema normalization.
- **Composable Unique Constraints**: Compound uniqueness on `(agent_id, client_request_id)` and `(provider, event_id)` implemented at the database engine level, not merely application code.

---

## 2. Entity Relational Model (ERD)

```
┌────────────────────────────────────────────────────────┐
│                    policy_requests                     │
├────────────────────────────────────────────────────────┤
│ PK  id                          BIGINT GENERATED       │
│ UQ1 agent_id                    VARCHAR(64)            │
│ UQ1 client_request_id           VARCHAR(128)           │
│     customer_name               VARCHAR(255)           │
│     date_of_birth               DATE                   │
│     destination                 VARCHAR(32)            │
│     trip_start                  DATE                   │
│     trip_end                    DATE                   │
│     travel_days                 INTEGER                │
│     plan_code                   VARCHAR(32)            │
│     sum_insured                 DECIMAL(12,2)          │
│     base_daily_rate             DECIMAL(10,2)          │
│     age_multiplier              DECIMAL(4,2)           │
│     destination_multiplier      DECIMAL(4,2)           │
│     coverage_multiplier         DECIMAL(4,2)           │
│     final_premium               DECIMAL(10,2)          │
│     status                      VARCHAR(48)            │
│ UQ2 policy_number               VARCHAR(64) NULLABLE   │
│ IX  provider_reference          VARCHAR(128) NULLABLE  │
│     attempt_count               INTEGER DEFAULT 0      │
│     created_at                  TIMESTAMP              │
│     updated_at                  TIMESTAMP              │
└──────────────┬──────────────────────────┬──────────────┘
               │ 1                        │ 1
               │                          │
               │ N                        │ N
┌──────────────▼─────────────┐ ┌──────────▼──────────────┐
│     issuance_attempts      │ │  policy_state_history   │
├────────────────────────────┤ ├─────────────────────────┤
│ PK  id             BIGINT  │ │ PK  id          BIGINT  │
│ FK  policy_req_id  BIGINT  │ │ FK  policy_req_id BIGINT│
│     attempt_number INT     │ │     from_state  VARCHAR │
│     provider       VARCHAR │ │     to_state    VARCHAR │
│     scenario       VARCHAR │ │     trigger     VARCHAR │
│     request_body   JSONB   │ │     reason      TEXT    │
│     response_code  INT     │ │     metadata    JSONB   │
│     response_body  JSONB   │ │     created_at  TIMESTMP│
│     error_detail   TEXT    │ └─────────────────────────┘
│     status         VARCHAR │
│     attempted_at   TIMESTMP│
│     completed_at   TIMESTMP│
└────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│                    provider_events                     │
├────────────────────────────────────────────────────────┤
│ PK  id                          BIGINT GENERATED       │
│ UQ  provider                    VARCHAR(64)            │
│ UQ  event_id                    VARCHAR(128)           │
│     event_type                  VARCHAR(64)            │
│ FK  policy_request_id           BIGINT NULLABLE        │
│     provider_reference          VARCHAR(128) NULLABLE  │
│     payload                     JSONB                  │
│     processed                   BOOLEAN DEFAULT FALSE  │
│     created_at                  TIMESTAMP              │
│     updated_at                  TIMESTAMP              │
└────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│           mock_insurer_policies (Isolated)             │
├────────────────────────────────────────────────────────┤
│ PK  id                          BIGINT GENERATED       │
│ UQ  client_request_id           VARCHAR(128)           │
│     agent_id                    VARCHAR(64)            │
│ UQ  policy_number               VARCHAR(64)            │
│ UQ  provider_reference          VARCHAR(128)           │
│     status                      VARCHAR(32)            │
│     created_at                  TIMESTAMP              │
│     updated_at                  TIMESTAMP              │
└────────────────────────────────────────────────────────┘
```

---

## 3. Strict Database Constraints & Invariants

### A. Idempotency Key Constraint
```sql
ALTER TABLE policy_requests 
ADD CONSTRAINT uq_policy_requests_agent_client_req 
UNIQUE (agent_id, client_request_id);
```
- **Rationale**: Guarantees at the database kernel level that no two workers or threads can ever insert duplicate intake records for the same client request.
- **Handling**: Caught via `UniqueConstraintViolationException` (or handled via `firstOrCreate` / `insertOrIgnore` under a transaction), returning HTTP 200 with the existing record.

### B. Global Policy Number Uniqueness
```sql
ALTER TABLE policy_requests 
ADD CONSTRAINT uq_policy_requests_policy_number 
UNIQUE (policy_number);
```
- **Rationale**: A policy number issued by an insurer can belong to at most one request. Nullable initially, populated once status becomes `ISSUED`.

### C. Provider Event Deduplication
```sql
ALTER TABLE provider_events 
ADD CONSTRAINT uq_provider_events_provider_event 
UNIQUE (provider, event_id);
```
- **Rationale**: Webhook replays from the insurer are rejected or ignored safely when `event_id` is re-delivered.

### D. Decimal-Safe Pricing Snapshot
- Currency values are never stored as floating-point numbers.
- `final_premium`, `base_daily_rate`, `sum_insured` use `DECIMAL(12,2)` / `DECIMAL(10,2)`. Multipliers use `DECIMAL(4,2)`.
- The snapshot fields are filled **once** during `VALIDATED` transition and are marked immutable by domain application logic.

---

## 4. Policy State Machine & Valid Transitions

| From State | Allowed To States | Trigger / Condition |
| :--- | :--- | :--- |
| `RECEIVED` | `VALIDATED`, `FAILED` | Validated by business rules & pricing calculated. |
| `VALIDATED` | `SUBMITTED`, `FAILED` | Submit endpoint called or queued for issuance. |
| `SUBMITTED` | `ISSUED`, `PENDING_CONFIRMATION`, `NEEDS_RECONCILIATION`, `FAILED` | Provider sync success, timeout, or non-retriable 4xx/5xx. |
| `PENDING_CONFIRMATION` | `ISSUED`, `FAILED`, `NEEDS_RECONCILIATION` | Webhook callback received or reconciliation query confirms state. |
| `NEEDS_RECONCILIATION` | `ISSUED`, `FAILED`, `SUBMITTED` (retry) | Automated reconciliation job or operator intervention. |
| `ISSUED` | **[TERMINAL STATE]** | **No transitions allowed.** Any attempt to overwrite `ISSUED` with `SUBMITTED`, `FAILED`, etc. is rejected. |
| `FAILED` | `SUBMITTED` (Only if retriable failure explicitly allowed) | Strict condition: must preserve original premium snapshot. |
