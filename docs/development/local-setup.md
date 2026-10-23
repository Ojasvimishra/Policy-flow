# Local Development Environment Setup

This document provides step-by-step instructions for running PolicyFlow both with Docker Compose (recommended) and natively on macOS/Linux.

---

## Method 1: Docker Compose Setup (Recommended)

### Prerequisites
- Docker Engine 24+ and Docker Compose v2+ installed.

### Step 1: Environment Preparation
```bash
# Copy environment configuration
cp .env.example .env
```

### Step 2: Build and Start Containers
```bash
docker compose up -d --build
```
This launches:
- `policyflow-app` (Laravel API on `http://localhost:8000`)
- `policyflow-db` (PostgreSQL 16 on port 5432)
- `policyflow-redis` (Redis 7 on port 6379)
- `policyflow-queue-worker` (Background worker processing `redis` queue)
- `policyflow-frontend` (Vite dev server on `http://localhost:5173`)

### Step 3: Application Initialization
```bash
# Install PHP dependencies
docker compose exec app composer install

# Generate application encryption key
docker compose exec app php artisan key:generate

# Execute database migrations
docker compose exec app php artisan migrate
```

### Step 4: Verify Running Services
```bash
docker compose ps
docker compose logs -f app
```

---

## Method 2: Native Setup (macOS / Linux)

### Prerequisites
- PHP 8.2 or newer with extensions: `pdo`, `pdo_pgsql` (or `pdo_mysql`), `bcmath`, `pcntl`, `redis`.
- Composer 2.x
- PostgreSQL 16 (or MySQL 8+)
- Redis 7+ (can be installed via Homebrew: `brew install redis`)
- Node.js 20+ and npm 10+ (for frontend)

### Installation Steps
```bash
# 1. Clone repository & configure environment
cp .env.example .env

# 2. Update .env for local database credentials:
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=policyflow
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# 3. Start local Redis service
brew services start redis   # on macOS

# 4. Install PHP dependencies
composer install

# 5. Generate application key
php artisan key:generate

# 6. Run database migrations
php artisan migrate

# 7. Start Laravel HTTP Server
php artisan serve --port=8000

# 8. Start Background Queue Worker (in a separate terminal)
php artisan queue:work redis --queue=issuance,callbacks,reconciliation,default --sleep=3 --tries=3
```

---

## Service Teardown & Maintenance
```bash
# Stop Docker containers
docker compose down

# Stop Docker containers and destroy database volumes
docker compose down -v
```
