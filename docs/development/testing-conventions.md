# Testing Conventions & Test Strategy

## 1. Test Philosophy & Standards
PolicyFlow's automated test suite is the cornerstone of reliability verification. Tests verify:
- Exact business pricing calculations across all boundaries and multipliers.
- Concurrency and idempotency under duplicated client requests.
- Fault recovery when external dependencies fail, time out, or re-send events.

---

## 2. Test Suite Organization

```
tests/
├── Unit/
│   ├── Pricing/
│   │   ├── PremiumCalculationTest.php      # Validates formula, rounding, multiplier edge cases
│   │   └── TravelDaysCalculationTest.php   # Inclusive date difference logic
│   └── StateMachine/
│       └── PolicyStateTransitionTest.php   # Rejects illegal state regressions (e.g. ISSUED -> SUBMITTED)
├── Feature/
│   ├── Intake/
│   │   ├── PolicyIntakeValidationTest.php  # Invalid dates, unlisted plans, missing fields
│   │   └── DuplicateIntakeIdempotencyTest.php # Repeated submissions with same (agent_id, client_request_id)
│   ├── Issuance/
│   │   ├── SynchronousIssuanceTest.php     # Happy path issuance returning policy_number
│   │   └── TimeoutAfterIssueRecoveryTest.php # Scenario TIMEOUT_AFTER_ISSUE & reconciliation
│   └── Callback/
│       └── WebhookCallbackDeduplicationTest.php # Idempotent handling of repeated provider events
└── TestCase.php
```

---

## 3. Running Tests

```bash
# Run entire test suite using Docker
docker compose exec app php artisan test

# Run entire test suite natively
php artisan test

# Run specific test file
php artisan test tests/Feature/Intake/DuplicateIntakeIdempotencyTest.php

# Run with coverage (requires PCOV or Xdebug)
php artisan test --coverage
```

---

## 4. Test Isolation & Database
In `phpunit.xml`:
- Test environment runs with an isolated database connection (in-memory SQLite or dedicated PostgreSQL test database `policyflow_test`).
- Transactions are refreshed per test via `Illuminate\Foundation\Testing\RefreshDatabase`.
- Queue connection is set to `sync` during automated tests to guarantee deterministic execution without requiring external background processes.
- The deterministic Mock Insurer simulator is invoked directly in HTTP feature tests without requiring external network connections.
