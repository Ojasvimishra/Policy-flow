# ADR-002: Database Selection — PostgreSQL 16 (with MySQL 8+ Compatibility)

## Status
Accepted

## Context
PolicyFlow requires strict transactional integrity, atomic duplicate detection under near-simultaneous intake requests, row-level locking during provider communication, and structured storage for raw JSON payloads from the mock insurer.

## Decision
We select **PostgreSQL 16** as the default primary database recommendation, while maintaining strict SQL compatibility with **MySQL 8+** (as an assignment-compliant alternative).
- Migrations will use standard Laravel schema definitions (`bigIncrements`, `unique()`, `decimal()`, `jsonb()` / `json()`).
- Atomic uniqueness constraints will be enforced at the database level on `(agent_id, client_request_id)` and `(provider, event_id)`.
- Concurrent mutations will utilize `lockForUpdate()` within explicit database transactions.

## Alternatives Considered
1. **MySQL 8+ as Default**:
   - Supported and fully compliant. PostgreSQL was chosen as default due to superior native JSONB indexing and strict ACID compliance, but MySQL configuration is provided in `.env.example`.
2. **MongoDB / NoSQL**:
   - *Rejected*: Lack of native relational foreign keys and complex distributed transactional semantics make NoSQL unsuitable for an insurance policy ledger.
3. **SQLite**:
   - Permitted only for rapid in-memory unit tests; disallowed for production/concurrency verification due to table-level lock contention.

## Consequences
- **Positive**: Absolute protection against race conditions and phantom duplicate policy creation.
- **Negative**: Requires running a PostgreSQL container or local service.
