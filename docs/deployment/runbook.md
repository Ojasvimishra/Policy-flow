# PolicyFlow Production Operations & Deployment Runbook

## 1. System Topology & Request Architecture

PolicyFlow operates as a containerized, decoupled architecture optimized for concurrency resilience, deterministic financial calculations, and high availability.

### Real Request Path
```
[ Client Browser / Travel Agent ]
                │
                ▼ (HTTP / HTTPS: 80 / 443)
┌────────────────────────────────────────────────────────┐
│ Edge Reverse Proxy & Static File Server (Nginx Alpine)  │
│ - Serves pre-compiled React SPA bundle from disk       │
│ - Security headers, gzip compression, asset caching   │
│ - SPA routing: try_files $uri $uri/ /index.html       │
└───────────────────────┬────────────────────────────────┘
                        │
       ┌────────────────┴────────────────┐
       │ /api/* or /up                   │ Static assets (.js, .css, images)
       ▼ (FastCGI: 9000)                 ▼
┌───────────────────────────────┐ ┌──────────────────────┐
│ PolicyFlow Backend (PHP-FPM)  │ │ Served directly from │
│ - Hardened PHP 8.2 Alpine     │ │ Nginx static cache   │
│ - Non-root execution: www-data│ └──────────────────────┘
│ - OPcache enabled             │
└───────────────┬───────────────┘
                │
      ┌─────────┴─────────┐
      ▼                   ▼
┌──────────────┐   ┌──────────────┐
│ PostgreSQL 16│   │   Redis 7    │
│ Primary DB   │   │ Queue, Cache │
│ ACID Stores  │   │ & Sessions   │
└──────────────┘   └──────┬───────┘
                          │
            ┌─────────────┴─────────────┐
            ▼                           ▼
┌────────────────────────┐  ┌────────────────────────┐
│  Queue Worker (Redis)  │  │  Scheduler / Reconciler│
│  Asynchronous provider │  │  Periodic automated    │
│  dispatches & jobs     │  │  reconciliation passes │
└────────────────────────┘  └────────────────────────┘
```

---

## 2. Production Environment Preparation

### Prerequisites
- Docker Engine 24+ and Docker Compose v2.20+
- Host operating system: Linux (Ubuntu 22.04 LTS / Debian 12 / RHEL 9 recommended)
- Minimum server specs: 2 vCPU, 4GB RAM, 20GB SSD storage
- Network firewalls: Ports 80 (HTTP) and 443 (HTTPS) accessible; ports 5432 and 6379 strictly closed to external traffic.

### Step 1: Clone Repository & Configure Target
```bash
git clone https://github.com/Ojasvimishra/Policy-flow.git /opt/policyflow
cd /opt/policyflow
```

### Step 2: Environment Secret Provisioning
Create the production environment file from the hardened template:
```bash
cp .env.production.example .env.production
chmod 600 .env.production
```

Generate a secure application key:
```bash
# Generate key using a disposable container
docker run --rm -v $(pwd):/var/www/html composer:2 sh -c "php -r 'echo \"base64:\" . base64_encode(random_bytes(32)) . PHP_EOL;'"
```
Paste this generated key into `APP_KEY` inside `.env.production`.

Generate strong passwords for PostgreSQL and Redis and update:
- `DB_PASSWORD`
- `REDIS_PASSWORD`

---

## 3. Deployment Procedures

### Initial Clean Deployment
```bash
# 1. Build and pull production images
docker compose -f docker-compose.prod.yml build

# 2. Start core database and cache backends
docker compose -f docker-compose.prod.yml up -d db redis

# 3. Wait for database and redis healthchecks
docker compose -f docker-compose.prod.yml ps

# 4. Execute database migrations safely (isolated runner)
docker compose -f docker-compose.prod.yml --profile migration run --rm migration

# 5. Launch web application runtime and background workers
docker compose -f docker-compose.prod.yml up -d app queue-worker scheduler nginx

# 6. Verify end-to-end health check
curl -f http://localhost/api/health
```

### Zero-Downtime Rolling Update Procedure
1. **Pull and build new artifacts:**
   ```bash
   git pull origin main
   docker compose -f docker-compose.prod.yml build
   ```

2. **Run backwards-compatible database migrations first:**
   ```bash
   docker compose -f docker-compose.prod.yml --profile migration run --rm migration
   ```

3. **Restart background workers gracefully:**
   ```bash
   docker compose -f docker-compose.prod.yml restart queue-worker scheduler
   ```

4. **Reload PHP-FPM and reload Nginx proxy:**
   ```bash
   docker compose -f docker-compose.prod.yml up -d --no-deps app
   docker compose -f docker-compose.prod.yml exec nginx nginx -s reload
   ```

5. **Verify health status:**
   ```bash
   curl -f http://localhost/up
   curl -f http://localhost/api/health
   ```

---

## 4. Controlled Database Migration Strategy

### The Single-Runner Invariant
**CRITICAL RULE:** Database migrations must NEVER be executed automatically in container entrypoints across scalable web replicas. Doing so creates concurrent schema lock contention, deadlock failures, and corrupted migration records.

### Safe Migration Execution
Always execute migrations through the dedicated, single-execution profile:
```bash
docker compose -f docker-compose.prod.yml --profile migration run --rm migration
```

### Migration Status Inspection
```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate:status
```

---

## 5. Rollback Playbook

### Scenario A: Application Code / Frontend Defect (No Database Schema Change)
1. Check out the previous stable release commit or tag:
   ```bash
   git checkout <PREVIOUS_STABLE_TAG_OR_COMMIT>
   ```
2. Rebuild and restart containers:
   ```bash
   docker compose -f docker-compose.prod.yml build
   docker compose -f docker-compose.prod.yml up -d app nginx queue-worker scheduler
   ```
3. Flush application opcode and configuration caches:
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
   docker compose -f docker-compose.prod.yml exec app php artisan config:cache
   docker compose -f docker-compose.prod.yml exec app php artisan route:cache
   ```

### Scenario B: Database Migration Rollback Required
1. Stop queue workers and reconcilers immediately to prevent in-flight schema conflicts:
   ```bash
   docker compose -f docker-compose.prod.yml stop queue-worker scheduler
   ```
2. Revert the last migration step:
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan migrate:rollback --step=1
   ```
3. Switch application containers to compatible version:
   ```bash
   git checkout <PREVIOUS_STABLE_COMMIT>
   docker compose -f docker-compose.prod.yml build
   docker compose -f docker-compose.prod.yml up -d
   ```

---

## 6. Disaster Recovery & Backup / Restore

### Automated Daily Database Backup
Execute via daily cron on the host machine:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/policyflow"
mkdir -p "$BACKUP_DIR"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
FILENAME="$BACKUP_DIR/policyflow_db_$TIMESTAMP.sql.gz"

docker compose -f /opt/policyflow/docker-compose.prod.yml exec -T db \
    pg_dump -U policyflow_user -d policyflow --clean --if-exists | gzip > "$FILENAME"

# Retain backups for 30 days
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +30 -delete
```

### Point-in-Time Database Restore
```bash
# 1. Stop all write traffic
docker compose -f docker-compose.prod.yml stop app queue-worker scheduler

# 2. Restore database from compressed backup
gunzip < /var/backups/policyflow/policyflow_db_TIMESTAMP.sql.gz | \
    docker compose -f docker-compose.prod.yml exec -T db psql -U policyflow_user -d policyflow

# 3. Restart application services
docker compose -f docker-compose.prod.yml up -d app queue-worker scheduler
```

---

## 7. Observability, Logging & Health Checks

### Endpoint Matrix
| Route | Method | Layer | Purpose | Expected Status |
| :--- | :--- | :--- | :--- | :--- |
| `/healthz` | GET | Edge Nginx | Verifies edge proxy is operational | `200 OK` ("OK") |
| `/up` | GET | Laravel Core | Standard Laravel 11 liveness probe | `200 OK` |
| `/api/health` | GET | Application API | Inspects PostgreSQL, Redis, and storage readiness | `200 OK` (or `503 Service Unavailable`) |

### Health Check Response Schema
```json
{
  "status": "healthy",
  "app": "PolicyFlow",
  "environment": "production",
  "timestamp": "2026-10-24T00:25:00Z",
  "services": {
    "database": {
      "status": "up",
      "driver": "pgsql",
      "latency_ms": 1.45
    },
    "redis": {
      "status": "up",
      "latency_ms": 0.82
    },
    "storage": {
      "status": "up",
      "writable": true
    }
  }
}
```

### Log Streaming & Monitoring
All containers write JSON or formatted logs to stdout/stderr:
```bash
# Stream backend application logs
docker compose -f docker-compose.prod.yml logs -f --tail=100 app

# Stream queue worker processing logs
docker compose -f docker-compose.prod.yml logs -f --tail=100 queue-worker

# Stream edge web traffic access logs
docker compose -f docker-compose.prod.yml logs -f --tail=100 nginx
```
Logs are automatically rotated by Docker's `json-file` driver (`max-size: 50m`, `max-file: 5`) to prevent disk exhaustion.
