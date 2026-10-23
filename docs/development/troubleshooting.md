# Troubleshooting & Diagnostics

## Common Issues & Solutions

### 1. Database Connection Refused
**Symptom**: `SQLSTATE[08006] [7] could not connect to server: Connection refused`.
- **Docker Fix**: Ensure PostgreSQL container is healthy:
  ```bash
  docker compose ps
  docker compose logs db
  ```
- **Native Fix**: Verify local PostgreSQL service is running and credentials match `.env`:
  ```bash
  pg_isready -h 127.0.0.1 -p 5432
  ```

### 2. Redis Queue Connection Timeout
**Symptom**: `Connection refused [tcp://127.0.0.1:6379]`.
- **Cause**: Redis server is stopped.
- **Resolution**:
  - Docker: `docker compose up -d redis`
  - macOS Native: `brew services start redis`
  - Quick Test: `redis-cli ping` (should output `PONG`).

### 3. Queue Jobs Not Processing
**Symptom**: Jobs are dispatched to Redis, but policy status remains `SUBMITTED` or `VALIDATED`.
- **Cause**: Queue worker is not running.
- **Resolution**:
  - Check worker status: `docker compose logs -f queue-worker`
  - Restart worker locally:
    ```bash
    php artisan queue:restart
    php artisan queue:work redis
    ```

### 4. Application Key Missing
**Symptom**: `No application encryption key has been specified.`
- **Resolution**:
  ```bash
  php artisan key:generate
  ```

### 5. Unique Constraint Violation in Tests
**Symptom**: `duplicate key value violates unique constraint "uq_policy_requests_agent_client_req"`
- **Resolution**: Ensure tests use `Illuminate\Foundation\Testing\RefreshDatabase` so tables are wiped cleanly between test runs, or use unique IDs with Faker (`'AGT-' . $this->faker->uuid()`).
