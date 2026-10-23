# PolicyFlow: Known Limitations & Architectural Assumptions

**Project:** TrippersShield / PolicyFlow  
**Document Purpose:** Transparent disclosure of design trade-offs, scope boundaries, and evaluation assumptions.  
**Author:** Ojasvi Mishra (`ojasvimishra9792@gmail.com`)  

---

## 1. Authentication & Identity Scoping

- **Assessment Assumption**:  
  In the current implementation, partner travel agents identify themselves through the `agent_id` field within the intake request payload. Client request idempotency is scoped strictly per agent `(agent_id, client_request_id)`.
- **Production Roadmap**:  
  In a multi-tenant enterprise deployment, identity should be verified cryptographically via an API Gateway or OAuth 2.0 / JWT bearer tokens (e.g., Laravel Passport or Sanctum). The authenticated token claim would inject the verified `agent_id` server-side, preventing any potential payload spoofing across agents.

---

## 2. Deterministic Mock Insurer Transport

- **Assessment Assumption**:  
  To guarantee 100% reproducible local assessment evaluation without requiring paid third-party cloud sandbox subscriptions, external provider communication is simulated using an internal deterministic mock insurer subsystem (`/api/mock-insurer`).
- **Production Roadmap**:  
  The mock insurer implements the identical HTTP request/response semantics, status query contracts, and asynchronous webhook delivery payloads specified in the domain design. Transitioning to a real insurer requires only swapping `MockInsurerClient` with a concrete provider adapter implementing the same interface.

---

## 3. Single-Node vs Distributed Clustering

- **Assessment Assumption**:  
  The provided Docker Compose configuration (`docker-compose.prod.yml`) is optimized for single-host production deployments (Edge Nginx, PHP-FPM, PostgreSQL, Redis, Queue Worker, and Scheduler).
- **Production Roadmap**:  
  For high-throughput multi-region clustering, the container setup can be translated directly into Kubernetes manifests (Helm charts). State stores (PostgreSQL and Redis) would be delegated to managed cloud services (e.g., AWS Aurora PostgreSQL and ElastiCache Redis) with multi-AZ failover and read replicas.

---

## 4. Local Host PHP 8.5 Deprecation Handling

- **Assessment Assumption**:  
  When running natively on developer machines equipped with pre-release or early PHP 8.5 runtimes, standard PDO database constants emit minor deprecation warnings from within Laravel vendor code.
- **Remediation Implemented**:  
  Cleanly suppressed via `error_reporting(E_ALL & ~E_DEPRECATED)` and `ini_set('display_errors', '0')` in `public/index.php` and `bootstrap/app.php`, preventing any HTML output corruption in JSON API responses. In addition, Composer platform constraints are explicitly pinned to `"platform": { "php": "8.2.0" }`, and production container images run stable, security-patched PHP 8.2-FPM Alpine.

---

## 5. Secret Management & Key Provisioning

- **Assessment Assumption**:  
  In strict accordance with security directives, no production encryption keys (`APP_KEY`) or production database credentials are committed to version control.
- **Operator Action Required**:  
  When spinning up a new production instance, the operator must execute `cp .env.production.example .env.production` and generate a unique key using `php artisan key:generate --show`.
