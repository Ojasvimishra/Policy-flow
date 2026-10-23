# AI Usage Disclosure & Verification Report

## 1. Compliance Statement
In accordance with the software engineering practical assessment guidelines, this document provides a transparent, factual disclosure of artificial intelligence assistance utilized in the architecture, engineering, and documentation of the **TrippersShield / PolicyFlow** platform across all eight development phases.

All business logic, mathematical calculations, database constraints, concurrency protocols, and test suites were designed, audited, and verified under strict human engineering oversight.

---

## 2. Tools & Models Utilized
- **AI Coding Assistant**: Antigravity IDE (Gemini 3.8 Flash, Claude 3.5 Sonnet, Advanced Agentic Coding).
- **Scope of Use**:
  - System architecture modeling and sequence diagram drafting.
  - Test scenario structure synthesis (Unit, Feature, and Concurrency test suites).
  - Docker Compose and multi-stage Dockerfile configuration.
  - GitHub Actions CI/CD workflow drafting.
  - Technical documentation, runbooks, and audit matrices.

---

## 3. Concrete Ways AI Assisted
1. **Sequence Modeling & Distributed Hazards**: Assisted in mapping the 5 critical failure scenarios (Success, Temporary 503, Timeout After Issue, Delayed Callback, and Duplicate Callback), formalizing the two-phase lock reservation model in `docs/architecture/request-lifecycle.md`.
2. **Database Invariants & Schema Design**: Accelerated the drafting of migration files with compound uniqueness constraints (`uq_policy_requests_agent_client_req`, `uq_issuance_attempts_policy_attempt`, and `uq_provider_events_provider_event`).
3. **Multi-Stage Container Hardening**: Structured multi-stage production Dockerfiles for PHP 8.2-FPM Alpine (with OPcache, non-root user, and PECL redis) and Node 22 / Nginx Alpine for static React SPA delivery.
4. **Automated CI/CD Pipeline**: Drafted the GitHub Actions workflow (`.github/workflows/ci.yml`) matrixing PHP 8.2 & 8.3 with live PostgreSQL 16 and Redis 7 service containers.
5. **Comprehensive Audit Documentation**: Generated markdown scaffolds for acceptance matrices, test results logs, and runbooks.

---

## 4. Verification & Testing Methodology
- **Strict Formula Validation**: Every rate, multiplier, and boundary condition was strictly cross-referenced against the authoritative specification. The ₹225.00 reference calculation was independently proven in both PHP (`bcmath`) and TypeScript (`Vitest`).
- **Real Database & Concurrency Verification**: Idempotency and locking mechanisms were not simulated with mocks; they were verified using real multi-threaded Python suites against live PostgreSQL transactions.
- **Security & Secret Integrity**: All environment files (`.env.example`, `.env.production.example`) were verified to contain zero real credentials, ensuring `.gitignore` safely isolates secrets.

---

## 5. Incomplete / Incorrect Suggestions Identified & Overridden
1. **Horizon & Microservice Bloat**:
   - *AI Suggestion:* Recommended adding Laravel Horizon, standalone worker containers per queue, and Redis sentinel clustering.
   - *Engineering Override:* Rejected as unnecessary complexity violating the assessment’s emphasis on reproducibility and clean monolithic architecture. Standard Artisan `queue:work` with Redis provides minimal memory overhead and zero external dependencies.
2. **Blind Retry on Network Timeout**:
   - *AI Suggestion:* Proposed immediately retrying the insurer endpoint upon receiving an HTTP 504 socket timeout.
   - *Engineering Override:* Overridden to enforce the defensive pattern: transition request to `PENDING_CONFIRMATION` and query provider status first before reissuing, preventing duplicate policy creation.
3. **Database Migration in Web Entrypoint**:
   - *AI Suggestion:* Suggested executing `php artisan migrate` in the web container's entrypoint script.
   - *Engineering Override:* Overridden because running migrations on every web container boot causes race conditions and deadlock across scaled replicas. Implemented a dedicated `--profile migration` runner instead.
4. **Platform PHP Unpinned Dependencies**:
   - *AI Suggestion:* Ran `composer update` on a developer host running PHP 8.5 without platform pinning, which locked Symfony 8.1 components requiring PHP >= 8.4.1.
   - *Engineering Override:* Identified the CI failure on PHP 8.2/8.3 runners, pinned `"platform": { "php": "8.2.0" }` in `composer.json`, and re-resolved lockfiles to Symfony 7.4.x LTS.
