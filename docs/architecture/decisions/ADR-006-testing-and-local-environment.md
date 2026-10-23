# ADR-006: Testing Strategy & Local Execution Environment

## Status
Accepted

## Context
Reviewers evaluate software engineering assessments based on reproducible local execution, automated test coverage of critical failure modes, and clear documentation. Tests must run without paid cloud services, external credentials, or unstable network dependencies.

## Decision
1. **Automated Testing**:
   - Built on **PHPUnit 11** and Laravel's HTTP/database test assertion framework.
   - Core test suites will cover:
     - Valid intake & pricing calculation (including edge-case age and multiplier boundaries).
     - Validation rejections (invalid dates, unsupported plans, sum insured outside bounds).
     - Duplicate intake idempotency under identical `(agent_id, client_request_id)`.
     - Timeout after success reconciliation flow.
     - Duplicate webhook callback deduplication.
     - State machine transition guardrails (preventing invalid regressions from `ISSUED`).
2. **Local Environment**:
   - **Docker Compose**: Standard containerized setup bundling PHP 8.2 FPM, PostgreSQL 16, Redis 7, queue worker, and Node 22.
   - **Native Setup Alternative**: Documented setup for developers running native PHP 8.2+, Composer, PostgreSQL / SQLite, and Homebrew Redis.
3. **Deterministic Mock Insurer**:
   - Internal simulated provider endpoints (`/api/mock-insurer/issue`, `/api/mock-insurer/status`) configurable via headers (`X-Mock-Scenario: SUCCESS | TIMEOUT_AFTER_ISSUE | TEMPORARY_FAILURE | DELAYED_CALLBACK | DUPLICATE_CALLBACK`).

## Alternatives Considered
1. **Third-Party Mock Services (e.g. WireMock, Mockoon, Postman Mock Server)**:
   - *Rejected*: Adds unnecessary external tooling and dependency overhead. An embedded Laravel mock route is zero-dependency and directly accessible in PHPUnit tests.

## Consequences
- **Positive**: 100% reproducible locally, self-contained test execution, zero external API keys needed.
- **Negative**: Requires maintaining mock insurer behavior and scenario endpoints.
