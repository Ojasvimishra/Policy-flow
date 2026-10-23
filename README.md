# TrippersShield / PolicyFlow

> **Fault-Tolerant Travel Insurance Policy Issuance & Reconciliation Engine**  
> *Campus Placement Practical Assessment — Software Engineering*

---

## 1. Product & Problem Overview
**TrippersShield / PolicyFlow** is an enterprise-grade backend engine and operational portal designed to process travel insurance policy purchase requests submitted by partner travel agents.

In real-world insurance distribution, external insurer APIs are notoriously volatile: network calls encounter unexpected timeouts, webhook callbacks are duplicated, and responses can be delayed. A naive system that blindly retries requests or lacks strict idempotency will issue multiple duplicate policies for a single customer, creating severe financial and regulatory liability.

**PolicyFlow solves this problem through defensive distributed systems engineering:**
1. **Intake Idempotency**: Atomic compound uniqueness on `(agent_id, client_request_id)` guarantees that repeated submissions return the existing policy without duplicate processing.
2. **Deterministic Pricing**: Mathematical pricing calculation with decimal-safe arithmetic (`bcmath`) storing an immutable premium snapshot.
3. **Defensive Timeout Recovery**: An insurer timeout never triggers a blind reissue. The request is placed in `PENDING_CONFIRMATION` and reconciled against the provider's authoritative status endpoint.
4. **Callback Deduplication**: Webhooks are uniquely deduplicated using provider event identifiers.
5. **Full State Auditability**: Every state transition is recorded in an immutable ledger table.

---

## 2. Technology Stack

| Domain | Technology | Version | Purpose |
| :--- | :--- | :--- | :--- |
| **Backend Framework** | Laravel | 11.x | REST API routing, FormRequests, Eloquent ORM, Queues |
| **Runtime Language** | PHP | 8.2+ | Strict typing, native exceptions, `bcmath` decimal math |
| **Primary Database** | PostgreSQL | 16 | ACID transactions, row-level locking (`FOR UPDATE`), JSONB |
| **Alternative Database** | MySQL | 8+ | Supported assignment-compliant alternative |
| **Cache & Queue** | Redis | 7+ / 8+ | Asynchronous job queues, dead-letter tracking, temporary locks |
| **Frontend UI** | React + Vite | React 18, TS 5 | Operational management dashboard, timeline visualization |
| **CSS System** | Tailwind CSS | 3.x | Clean responsive operational styling |
| **Containerization** | Docker Compose | 3.8 | Zero-configuration reproducible local development |
| **Testing** | PHPUnit | 11.x | Automated unit, feature, and failure scenario testing |
| **Code Style** | Laravel Pint | 1.13+ | Automated PSR-12 code formatting |

---

## 3. Current Implementation Status

```
[X] Phase 0: Project Foundation, Architecture & Environment Setup  ──▶ COMPLETED
[X] Phase 1: Database Implementation & Domain Core                 ──▶ COMPLETED
[X] Phase 2: Policy Intake, Validation, Pricing & Idempotency      ──▶ COMPLETED
[X] Phase 3: Issuance Engine, Mock Insurer, Callbacks & Recon      ──▶ COMPLETED
[X] Phase 4: Frontend & Operational User Experience                ──▶ COMPLETED
[X] Phase 5: Integration & Reliability Hardening                   ──▶ COMPLETED
[X] Phase 6: Automated Testing, Security & Quality Verification    ──▶ COMPLETED
[X] Phase 7: Production Deployment Readiness & Docker Hardening    ──▶ COMPLETED
[X] Phase 8: Final Acceptance, Release Validation & Submission     ──▶ COMPLETED
```

> **Final Acceptance Status: PASS (100% COMPLETE)**  
> All 8 phases are implemented, audited, and verified against the authoritative specification. 52 backend tests, 8 frontend tests, 11 multi-worker concurrency scenarios, and the GitHub Actions CI/CD pipeline are passing with 100% success. Ready for submission.

---

## 4. Repository Structure

```
Policy-flow/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # API & Webhook controllers
│   │   ├── Requests/             # FormRequest validation rules
│   │   └── Resources/            # JSON API response transformers
│   ├── Models/                   # Eloquent models with mass-assignment guards
│   ├── Services/                 # Domain business services (Intake, Pricing, Issuance)
│   ├── Jobs/                     # Asynchronous Redis queue jobs
│   ├── Events/ & Listeners/      # Internal domain event dispatching
│   ├── Policies/                 # Authorization policies
│   └── Providers/                # Service providers
├── bootstrap/                    # Framework boot configuration
├── config/                       # Application configuration
├── database/
│   ├── factories/                # Model factories for testing
│   ├── migrations/               # Database migration files (source of truth)
│   └── seeders/                  # Test scenario seeders
├── docs/
│   ├── architecture/             # Architecture, database schema, queues, security
│   │   └── decisions/            # Architecture Decision Records (ADR-001 - ADR-006)
│   ├── development/              # Setup, testing, coding standards, troubleshooting
│   └── DEVELOPMENT_ROADMAP.md    # Complete 8-Phase implementation roadmap
├── examples/                     # Sample JSON payloads & curl execution scripts
├── frontend/                     # React + Vite operational interface
├── public/                       # Web root
├── routes/                       # REST API & Console routes
├── scripts/                      # Setup & diagnostic helper scripts
├── storage/                      # Framework logs and cache
├── tests/
│   ├── Unit/                     # Pricing & state machine unit tests
│   ├── Feature/                  # API, idempotency, timeout & callback tests
│   └── Integration/              # End-to-end integration tests
├── .env.example                  # Comprehensive placeholder configuration
├── .gitignore                    # Robust secret & artifact exclusion rules
├── AI_USAGE.md                   # AI usage disclosure and engineering verification
├── composer.json                 # Laravel 11 dependency specification
├── docker-compose.yml            # Multi-container orchestration (App, DB, Redis, Worker)
├── Dockerfile                    # Container definition for PHP 8.2 FPM / CLI
└── README.md                     # This file
```

---

## 5. Deployment & Quick Start Workflows

### Option A: Hardened Production Docker Deployment (Recommended for Production)

PolicyFlow includes a hardened multi-stage container deployment topology with Edge Nginx, PHP 8.2-FPM Alpine, PostgreSQL 16 Alpine, and Redis 7 Alpine.

1. **Configure Production Environment**:
   ```bash
   cp .env.production.example .env.production
   chmod 600 .env.production
   # Update APP_KEY, DB_PASSWORD, and REDIS_PASSWORD in .env.production
   ```

2. **Build Production Containers**:
   ```bash
   docker compose -f docker-compose.prod.yml build
   ```

3. **Start Core Databases**:
   ```bash
   docker compose -f docker-compose.prod.yml up -d db redis
   ```

4. **Run Controlled Database Migrations (Zero Migration Races)**:
   ```bash
   docker compose -f docker-compose.prod.yml --profile migration run --rm migration
   ```

5. **Launch Application & Background Workers**:
   ```bash
   docker compose -f docker-compose.prod.yml up -d app queue-worker scheduler nginx
   ```
   *Production endpoints*:
   - Web Portal & API: `http://localhost/` (Edge Nginx reverse proxy)
   - Health Probes: `http://localhost/api/health` and `http://localhost/up`

### Option B: Local Development Docker Compose

1. **Clone and Configure Environment**:
   ```bash
   cp .env.example .env
   ```

2. **Build and Start Local Development Containers**:
   ```bash
   docker compose up -d --build
   ```
   *Services launched*:
   - `policyflow-app`: `http://localhost:8000` (Laravel API)
   - `policyflow-db`: PostgreSQL 16 on port `5432`
   - `policyflow-redis`: Redis 7 on port `6379`
   - `policyflow-queue-worker`: Processing `redis` queue
   - `policyflow-scheduler`: Scheduled reconciliation worker
   - `policyflow-frontend`: Vite dev server on `http://localhost:5173`

3. **Initialize App & Run Migrations**:
   ```bash
   docker compose exec app composer install
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate
   ```

### Option B: Native macOS / Linux Setup

1. **Prerequisites**: PHP 8.2+, Composer 2+, PostgreSQL 16 (or MySQL 8+), Redis 7+.
2. **Start Local Redis**:
   ```bash
   brew services start redis  # macOS
   ```
3. **Configure & Migrate**:
   ```bash
   cp .env.example .env
   # Ensure DB_USERNAME & DB_PASSWORD match your local PostgreSQL/MySQL setup
   composer install
   php artisan key:generate
   php artisan migrate
   ```
4. **Start Web Server, Queue Worker & Operational Frontend**:
   ```bash
   # Terminal 1: HTTP API Server
   php artisan serve --port=8000

   # Terminal 2: Background Queue Worker
   php artisan queue:work redis --queue=issuance,callbacks,reconciliation,default --sleep=3 --tries=3

   # Terminal 3: PolicyFlow Operations Portal (Vite React + TypeScript)
   cd frontend
   npm install
   npm run dev  # Launched at http://localhost:5173
   ```

---

## 6. Running Automated Tests

```bash
# Run backend tests inside Docker
docker compose exec app php artisan test

# Run backend tests natively
php artisan test

# Run specific backend feature test
php artisan test tests/Feature/Intake/DuplicateIntakeIdempotencyTest.php

# Run frontend Vitest test suite (pricing engine, API clients)
cd frontend && npm test

# Run frontend TypeScript type-checking and production bundle build
cd frontend && npm run build
```

---

## 7. Architecture & Documentation Index

- [System Overview & Component Boundaries](docs/architecture/system-overview.md)
- [Request Lifecycle & Sequence Diagrams](docs/architecture/request-lifecycle.md)
- [Database Architecture & Schema Blueprint](docs/architecture/database-design.md)
- [Redis & Queue Architecture](docs/architecture/queue-and-reliability.md)
- [Security & Secret Management](docs/architecture/security.md)
- [Frontend Architecture Specification](docs/architecture/frontend-architecture.md)
- [Production Operations & Deployment Runbook](docs/deployment/runbook.md)
- [Phase 7 Deployment Readiness & Verification Report](docs/testing/phase-7-deployment-readiness.md)
- [Phase 8 Acceptance Matrix](docs/testing/phase-8-acceptance-matrix.md)
- [Phase 8 Final Audit Report](docs/testing/phase-8-final-audit-report.md)
- [Phase 8 Test Results Log](docs/testing/phase-8-test-results.md)
- [Submission Implementation Summary](docs/submission/implementation-summary.md)
- [Known Limitations & Design Assumptions](docs/submission/known-limitations.md)
- [Eight-Phase Development Roadmap](docs/DEVELOPMENT_ROADMAP.md)
- [Local Development Setup](docs/development/local-setup.md)
- [Testing Conventions](docs/development/testing-conventions.md)
- [Coding Standards & Pint Formatting](docs/development/coding-standards.md)
- [Troubleshooting & Diagnostics](docs/development/troubleshooting.md)
- [Architecture Decision Records (ADR-001 to ADR-006)](docs/architecture/decisions/)
- [AI Usage Disclosure](AI_USAGE.md)

---

## 8. Duplicate Prevention Strategy (Summary)

PolicyFlow enforces a three-tier defense against duplicate policies:
1. **Intake Layer**: A database constraint on `UNIQUE (agent_id, client_request_id)` prevents concurrent or repeated intake. A repeated submission performs an atomic lookup and returns HTTP 200 with the existing record and immutable calculated premium.
2. **Orchestration Layer**: Before calling the insurer, the policy is locked with `lockForUpdate()`. If already `ISSUED` or `IN_FLIGHT`, the call is aborted. Sequential attempts are logged in `issuance_attempts` with `UNIQUE(policy_request_id, attempt_number)`.
3. **Reconciliation on Timeout**: If an insurer call times out, the system marks the status as `PENDING_CONFIRMATION` instead of reissuing. It queries the insurer's `/api/mock-insurer/status` endpoint to confirm whether a policy was created before any subsequent action.

---

## 9. Phase 3 REST API & Reconciliation Reference

### Policy Submission for Issuance
- **Endpoint**: `POST /api/policy-requests/{id}/submit`
- **Headers**: `X-Mock-Scenario: SUCCESS | TEMPORARY_FAILURE | TIMEOUT_AFTER_ISSUE | DELAYED_CALLBACK | DUPLICATE_CALLBACK` (optional scenario override for deterministic testing)
- **Response**: HTTP 200 (Issued), HTTP 202 (Pending confirmation on timeout), or HTTP 503 (Temporary failure, retriable).

### Mock Insurer Gateway
- **Endpoint**: `POST /api/mock-insurer/issue`
- **Endpoint**: `GET /api/mock-insurer/status/{client_request_id}`

### Provider Webhook Callback
- **Endpoint**: `POST /api/mock-insurer/callback`
- **Payload**:
  ```json
  {
    "provider": "mock_insurer",
    "event_id": "EVT-MOCK-99901",
    "event_type": "POLICY_ISSUED",
    "client_request_id": "AGT-2026-0001",
    "provider_reference": "PRV-REF-123456",
    "policy_number": "POL-MOCK-ABC12345",
    "payload": {
      "status": "ISSUED"
    }
  }
  ```
- **Deduplication**: Enforced via DB constraint `UNIQUE (provider, event_id)` on `provider_events`. Replayed events return HTTP 200 with `idempotent_replay: true` and zero state mutations. Terminal `ISSUED` status cannot be regressed by late-arriving failure callbacks.

### Artisan Reconciliation Command
```bash
# Reconcile all stale or timed-out policies against insurer records
php artisan policyflow:reconcile --limit=100
```

---

## 10. Known Limitations & Simplifying Assumptions
- **Authentication**: For local evaluation, requests identify the agent via `agent_id` payload; OAuth/JWT authentication is deferred for production hardening.
- **Mock Insurer Transport**: The mock insurer is hosted locally as an internal sub-service (`/api/mock-insurer`) to eliminate any need for external API credentials or paid cloud sandboxes.
