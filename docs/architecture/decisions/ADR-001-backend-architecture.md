# ADR-001: Backend Architecture — Modular Laravel Monolith

## Status
Accepted

## Context
TrippersShield / PolicyFlow requires a robust, fault-tolerant policy issuance engine that handles intake, deterministic pricing, mock insurer communication, state tracking, and reconciliation. The system needs to be evaluated in a campus practical assessment context where code clarity, correctness, reproducible local setup, and testability within 8–12 hours are critical.

## Decision
We select a **Modular Laravel Monolith** built on **PHP 8.2+** and **Laravel 11.x**.
- All domain modules (Intake, Pricing, Issuance Orchestration, State Machine, Mock Insurer Adapter, Reconciliation) reside within a unified codebase under `app/Services/`.
- Controllers are strictly kept thin and delegate all business logic to dedicated Application Services.
- Relational persistence is managed via Laravel Eloquent and Database Migrations.

## Alternatives Considered
1. **Microservices Architecture (Separate Intake, Pricing, and Provider Services)**:
   - *Rejected*: Incurs massive distributed network overhead, complex local orchestration, split transactions, and excessive operational complexity incompatible with the assessment timeframe.
2. **Node.js / Express or NestJS**:
   - *Rejected*: Explicitly prohibited by the authoritative assignment requirements which mandate Laravel 11+.

## Consequences
- **Positive**: Single repository, instant local execution, transactional consistency, rapid test execution using Laravel's testing suite, clear module boundaries.
- **Negative**: Requires discipline to prevent coupling between services; mitigated by strict service boundaries and automated unit tests.
