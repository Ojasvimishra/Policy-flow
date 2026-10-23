# Security Architecture & Secret Management

## 1. Core Security Principles
PolicyFlow implements defense-in-depth principles across data intake, internal processing, external communication, and operational logging.

---

## 2. Secret Management & Environment Security
1. **Zero Secret Persistence in VCS**:
   - `.env` and all credential-bearing files are strictly excluded via `.gitignore`.
   - `.env.example` contains only placeholder values (`secret_local_password`, `http://localhost:8000`).
   - Production secrets must be injected through runtime container environments (Kubernetes Secrets, AWS SSM, or Docker Swarm secrets).
2. **Key Generation**:
   - `APP_KEY` must be generated using `php artisan key:generate` during initial installation.

---

## 3. Safe Logging & PII Masking
1. **PII Sanitization**:
   - Logs must never output sensitive customer personal identifiable information in plain text without redaction.
   - Specifically, `customer_name` is masked in logs (e.g., `S***** C*******`) and `date_of_birth` is truncated to birth year where diagnostic logging is required.
2. **Payload Masking**:
   - In `issuance_attempts` and `provider_events`, all incoming and outgoing JSON bodies are scrubbed of credentials, auth tokens, or sensitive headers before persistence.

---

## 4. Input Validation & Mass Assignment Guard
1. **FormRequest Whitelisting**:
   - Every API endpoint enforces strict Laravel FormRequests (`StorePolicyRequest`).
   - Fields are strongly typed: `date_of_birth` (date format `Y-m-d`), `trip_start` (>= today), `trip_end` (>= `trip_start`).
   - Enum whitelisting: `plan_code` in `['BASIC', 'PLUS', 'PREMIUM']`, `destination` in `['DOMESTIC', 'INTERNATIONAL']`, `sum_insured` in `[100000, 300000, 500000]`.
2. **Eloquent Mass Assignment Protection**:
   - All Eloquent models (`PolicyRequest`, `IssuanceAttempt`, etc.) define explicit `$fillable` arrays.
   - Status, policy numbers, and calculated premiums are strictly protected from client tampering and can only be set by dedicated service methods.

---

## 5. Mock Insurer Isolation & Guardrails
1. **Environment Gate**:
   - Mock insurer endpoints and scenario-forcing headers (e.g., `X-Mock-Scenario: TIMEOUT_AFTER_ISSUE`) are strictly disabled if `MOCK_INSURER_ENABLED=false` or if `APP_ENV === 'production'`.
2. **Isolated Database Storage**:
   - The mock provider stores simulated policies in a separate table (`mock_insurer_policies`) to prevent testing artifacts from polluting core financial ledger records.

---

## 6. Webhook Authenticity & Replay Protection
1. **Replay Defense**:
   - Every provider callback requires a provider-scoped `event_id`.
   - Database table `provider_events` maintains a unique constraint on `(provider, event_id)`. Any replayed event is detected and dropped idempotently.
2. **Signature Verification (Production Target)**:
   - Webhooks evaluate an `X-Provider-Signature` header (HMAC SHA-256) using a shared webhook secret. In local mock mode, a configurable mock token is accepted.

---

## 7. Standardized Error Envelope
API endpoints always return structured JSON envelopes, never exposing database query exceptions, file paths, or stack traces to clients:
```json
{
  "success": false,
  "error": {
    "code": "INVALID_STATE_TRANSITION",
    "message": "Policy #14 is in status ISSUED and cannot transition to SUBMITTED.",
    "details": []
  }
}
```
