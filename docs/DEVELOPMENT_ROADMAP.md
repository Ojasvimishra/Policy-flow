# TrippersShield / PolicyFlow: Eight-Phase Development Roadmap

## Executive Summary
This roadmap formalizes the end-to-end engineering plan for **PolicyFlow**, a fault-tolerant travel insurance policy issuance and reconciliation engine. The plan spans exactly eight distinct development phases (Phase 0 through Phase 8), prioritizing mandatory assignment requirements, business correctness, and reliability over cosmetic polish.

---

## Phase 0: Foundation, System Architecture & Environment Setup
- **Objective**: Establish the complete technical blueprint, environment specifications, architecture decision records, queue strategies, database schemas, and documentation.
- **Scope**: Repository audit, `.gitignore`, `.env.example`, Docker Compose specification, architecture diagrams, sequence models, ADR-001 through ADR-006, development roadmap, and Phase 1 backlog.
- **Dependencies**: None.
- **Ordered Tasks**:
  1. Audit existing repository state and environment tools (PHP, Composer, Docker, Node, Redis).
  2. Author complete system architecture diagrams and request sequence models.
  3. Formalize database schema blueprint with strict uniqueness constraints and state machine rules.
  4. Design Redis queue and reliability architecture.
  5. Establish Docker Compose environment and native setup alternatives.
  6. Create ADRs, testing conventions, and AI disclosure framework.
- **Expected Files**:
  - `README.md`, `AI_USAGE.md`, `.env.example`, `composer.json`, `docker-compose.yml`, `Dockerfile`
  - `docs/architecture/*`, `docs/architecture/decisions/*`, `docs/development/*`
  - `docs/DEVELOPMENT_ROADMAP.md`
- **Database Changes**: Schema documented; no live migrations run in Phase 0.
- **Backend Work**: Directory scaffolding, Composer dependency definitions.
- **Frontend Work**: Decoupled React/Vite architecture and domain type definitions documented.
- **Tests**: Testing strategy documented; test suites designed.
- **Acceptance Criteria**: All foundation documents, ADRs, Docker specifications, and roadmap published and validated.
- **Definition of Done**: Repository is clean, reproducible, and ready for immediate domain model migration in Phase 1.
- **Estimated Effort**: 1.5–2 hours.
- **Handoff to Phase 1**: Execution handoff report documenting environment findings and Phase 1 implementation backlog.

---

## Phase 1: Database Implementation & Domain Core [COMPLETED]
- **Status**: Completed & Verified (Commit `Phase 1`)
- **Objective**: Implement production-grade database migrations, Eloquent domain models, relationships, and the immutable state machine engine.
- **Scope**:
  - Migration creation: `policy_requests`, `issuance_attempts`, `provider_events`, `policy_state_history`, and isolated `mock_insurer_policies`.
  - Eloquent models with mass-assignment protections and type casts.
  - State machine transition validator preventing invalid regressions (e.g. `ISSUED` -> `SUBMITTED`).
- **Dependencies**: Phase 0 schema blueprint (`docs/architecture/database-design.md`).
- **Ordered Tasks**:
  1. Generate and implement migration `create_policy_requests_table`.
  2. Generate and implement migration `create_issuance_attempts_table`.
  3. Generate and implement migration `create_provider_events_table`.
  4. Generate and implement migration `create_policy_state_history_table`.
  5. Generate and implement migration `create_mock_insurer_policies_table`.
  6. Build `PolicyRequest`, `IssuanceAttempt`, `ProviderEvent`, and `PolicyStateHistory` models.
  7. Implement `PolicyStateTransitionService` with state transition matrices.
- **Expected Files**:
  - `database/migrations/*`
  - `app/Models/*`
  - `app/Services/PolicyStateTransitionService.php`
  - `tests/Unit/StateMachine/PolicyStateTransitionTest.php`
- **Database Changes**: Live execution of 5 migrations creating core tables and indexes.
- **Backend Work**: Eloquent model casts, relationships, and state validation logic.
- **Frontend Work**: None.
- **Tests**: Unit tests asserting valid and forbidden state transitions.
- **Acceptance Criteria**: Migrations run cleanly; models load and persist; illegal state transitions throw typed exceptions.
- **Definition of Done**: Schema committed and verified with passing unit tests.
- **Estimated Effort**: 1.5–2 hours.
- **Handoff to Phase 2**: Operational database and domain core ready for policy intake and pricing logic.

---

## Phase 2: Policy Intake, Validation, Premium Calculation & Idempotency [COMPLETED]
- **Status**: Completed & Verified (Commit `Phase 2`)
- **Objective**: Implement the REST API intake endpoint, input validation, deterministic decimal-safe pricing rules, and atomic duplicate intake protection.
- **Scope**:
  - `POST /api/policy-requests` endpoint.
  - `StorePolicyRequest` FormRequest with date, age, plan, and sum insured rules.
  - `PremiumCalculationService` implementing assignment formula using `bcmath`.
  - Atomic idempotency on `(agent_id, client_request_id)` returning HTTP 200 for existing records.
- **Dependencies**: Phase 1 migrations and models.
- **Ordered Tasks**:
  1. Implement `StorePolicyRequest` with comprehensive validation rules.
  2. Implement `PremiumCalculationService` adhering to exact assignment multipliers and rounding.
  3. Implement `PolicyIntakeService` handling atomic creation or lookup under transaction.
  4. Implement `PolicyRequestController@store` and `PolicyRequestResource`.
  5. Add automated unit tests for pricing boundaries (age thresholds 17, 18, 60, 61, 70, 71).
  6. Add automated feature tests for duplicate intake idempotency (concurrent & sequential).
- **Expected Files**:
  - `app/Http/Requests/StorePolicyRequest.php`
  - `app/Http/Controllers/Api/PolicyRequestController.php`
  - `app/Http/Resources/PolicyRequestResource.php`
  - `app/Services/PremiumCalculationService.php`
  - `app/Services/PolicyIntakeService.php`
  - `tests/Unit/Pricing/PremiumCalculationTest.php`
  - `tests/Feature/Intake/PolicyIntakeValidationTest.php`
  - `tests/Feature/Intake/DuplicateIntakeIdempotencyTest.php`
- **Database Changes**: None (schema established in Phase 1).
- **Backend Work**: Intake pipeline, API responses (HTTP 201 vs 200), and pricing engine.
- **Frontend Work**: None.
- **Tests**: Boundary pricing tests, date logic validation tests, duplicate request tests.
- **Acceptance Criteria**: Valid intake returns HTTP 201 with exact calculated premium snapshot; repeated intake returns HTTP 200 with existing record; invalid inputs return HTTP 422.
- **Definition of Done**: Complete intake API verified with passing automated test suite.
- **Estimated Effort**: 2 hours.
- **Handoff to Phase 3**: Intake requests persisted and validated, ready for mock insurer submission.

---

## Phase 3: Issuance Engine, Mock Insurer, Callbacks & Reconciliation
- **Objective**: Implement deterministic mock insurer, issuance orchestration, timeout handling, webhook callback processing, and status reconciliation.
- **Scope**:
  - Deterministic Mock Insurer supporting scenarios: `SUCCESS`, `TEMPORARY_FAILURE`, `TIMEOUT_AFTER_ISSUE`, `DELAYED_CALLBACK`, `DUPLICATE_CALLBACK`.
  - `POST /api/policy-requests/{id}/submit` endpoint and `SubmitPolicyForIssuance` job.
  - Safe timeout handling transitioning to `PENDING_CONFIRMATION` without blind reissuance.
  - `POST /api/mock-insurer/callback` webhook endpoint with deduplication.
  - `ReconciliationService` and `policyflow:reconcile` artisan command.
- **Dependencies**: Phase 1 & 2.
- **Ordered Tasks**:
  1. Build internal Mock Insurer routes and controller with isolated table storage.
  2. Implement `IssuanceOrchestrator` managing attempts and insurer HTTP client.
  3. Implement `ProviderCallbackService` with event deduplication.
  4. Implement `ReconciliationService` for querying provider status.
  5. Wire Redis queue jobs for asynchronous execution.
  6. Write automated tests for timeout recovery and duplicate callback handling.
- **Expected Files**:
  - `app/Services/MockInsurer/*`
  - `app/Services/IssuanceOrchestrator.php`
  - `app/Services/ProviderCallbackService.php`
  - `app/Services/ReconciliationService.php`
  - `app/Console/Commands/ReconcileStalePolicies.php`
  - `tests/Feature/Issuance/TimeoutAfterIssueRecoveryTest.php`
  - `tests/Feature/Callback/WebhookCallbackDeduplicationTest.php`
- **Database Changes**: None.
- **Backend Work**: Orchestration, mock endpoints, callbacks, and scheduled reconciliation.
- **Frontend Work**: None.
- **Tests**: Mock scenario tests, timeout recovery tests, callback deduplication tests.
- **Acceptance Criteria**: Guaranteed single policy number per request; timeout recovers safely via reconciliation without duplicate issuance; duplicated callbacks processed once.
- **Definition of Done**: All five required reliability scenarios tested and confirmed.
- **Estimated Effort**: 2.5–3 hours.
- **Handoff to Phase 4**: Issuance engine and API complete, ready for operational frontend integration.

---

## Phase 4: Frontend & Operational User Experience
- **Objective**: Deliver a clean, responsive operational portal in React / TypeScript for policy intake, tracking, timeline inspection, and manual reconciliation.
- **Scope**:
  - Vite React frontend in `frontend/`.
  - Policy intake form with real-time date and plan validation.
  - Policy list with status filters, search, and pagination.
  - Policy details view showing attempt timeline, raw provider payloads, and state audit trail.
  - Trigger buttons for manual submission and reconciliation.
- **Dependencies**: Phase 2 and Phase 3 REST endpoints.
- **Ordered Tasks**:
  1. Scaffold React + Vite + Tailwind application in `frontend/`.
  2. Build typed API client configured via `VITE_API_BASE_URL`.
  3. Implement Policy Intake form.
  4. Implement Policy List view with status badges.
  5. Implement Policy Detail and Timeline component.
- **Expected Files**:
  - `frontend/src/*`, `frontend/package.json`, `frontend/vite.config.ts`
- **Database Changes**: None.
- **Backend Work**: CORS configuration in `config/cors.php`.
- **Frontend Work**: UI components, responsive layout, and API integration.
- **Tests**: Component rendering and intake form validation tests.
- **Acceptance Criteria**: Real-time form submission communicates with backend; policies and timelines display accurately.
- **Definition of Done**: Frontend operational portal fully functional against local API.
- **Estimated Effort**: 2 hours.
- **Handoff to Phase 5**: End-to-end user experience and backend connected.

---

## Phase 5: End-to-End Integration & Reliability Hardening
- **Objective**: Harden edge cases, verify multi-worker queue concurrency, and ensure system-wide resilience under simulated failures.
- **Scope**:
  - Multi-worker queue testing.
  - Rapid parallel intake simulation (concurrency stress).
  - Unhandled exception recovery and dead-letter queue behavior.
  - End-to-end integration flow from intake through mock insurer callback and reconciliation.
- **Dependencies**: Phases 1–4.
- **Ordered Tasks**:
  1. Stress-test concurrent requests with identical idempotency keys.
  2. Verify queue worker recovery after forced container restart.
  3. Validate database connection pooling and deadlock avoidance.
- **Expected Files**:
  - `tests/Feature/Integration/EndToEndIssuanceLifecycleTest.php`
  - `scripts/stress_test_concurrency.sh`
- **Acceptance Criteria**: Zero duplicate records under concurrent load; queue recovers seamlessly after restart.
- **Definition of Done**: System verified robust under simulated production stress.
- **Estimated Effort**: 1 hour.
- **Handoff to Phase 6**: Hardened engine ready for formal verification and quality checks.

---

## Phase 6: Automated Testing, Security & Quality Verification
- **Objective**: Execute complete test suites, run static analysis, verify code formatting, and audit security configurations.
- **Scope**:
  - Run full PHPUnit suite with high assertion density.
  - Run Laravel Pint style checks.
  - Run static analysis (PHPStan / Larastan).
  - Audit `.gitignore` and ensure no secrets or test credentials are committed.
- **Dependencies**: All preceding code phases.
- **Ordered Tasks**:
  1. Execute `php artisan test` and achieve 100% pass rate.
  2. Execute `vendor/bin/pint` to enforce PSR-12 standard.
  3. Run static analysis checks.
  4. Verify PII masking in logs and sanitization in JSON responses.
- **Expected Files**:
  - Test reports, Pint configuration, static analysis baseline.
- **Acceptance Criteria**: 100% tests passing; zero Pint lint errors; zero exposed secrets.
- **Definition of Done**: Quality gate passed.
- **Estimated Effort**: 1 hour.
- **Handoff to Phase 7**: Verified codebase ready for final documentation and packaging.

---

## Phase 7: Documentation Completion & Local Delivery Packaging
- **Objective**: Finalize comprehensive user documentation, setup instructions, curl examples, and AI usage disclosure.
- **Scope**:
  - Update `README.md` with verified step-by-step run commands and architectural rationale.
  - Finalize `AI_USAGE.md` detailing tool assistance, verification steps, and engineering evaluations.
  - Provide runnable example scripts in `examples/` (`curl_commands.sh`).
- **Dependencies**: Phases 1–6.
- **Ordered Tasks**:
  1. Finalize `README.md`.
  2. Complete `AI_USAGE.md` with transparent disclosures.
  3. Verify `examples/` scripts run cleanly against local server.
- **Expected Files**:
  - `README.md`, `AI_USAGE.md`, `examples/*`
- **Acceptance Criteria**: A reviewer can clone the repo and execute setup and tests in under 5 minutes without ambiguity.
- **Definition of Done**: Documentation fully reflects the implemented system.
- **Estimated Effort**: 1 hour.
- **Handoff to Phase 8**: Ready for final acceptance review.

---

## Phase 8: Final Acceptance, Demonstration & Submission
- **Objective**: Conduct final end-to-end acceptance review, record demonstration evidence, and prepare the GitHub repository for submission.
- **Scope**:
  - Complete Phase 8 Acceptance Checklist.
  - Verify Git commit history (multiple meaningful commits, no squash).
  - Prepare 3–5 minute demonstration video/screen recording link.
  - Final repository push and submission to HR channel.
- **Dependencies**: All phases complete.
- **Ordered Tasks**:
  1. Execute final acceptance checklist.
  2. Verify repository accessibility and commit history.
  3. Record demonstration of setup, intake, pricing, timeout recovery, and duplicate callback handling.
  4. Push final commits to GitHub.
- **Acceptance Criteria**: Repository meets 100% of assignment requirements and scoring rubrics.
- **Definition of Done**: Final submission completed.
- **Estimated Effort**: 0.5–1 hour.
