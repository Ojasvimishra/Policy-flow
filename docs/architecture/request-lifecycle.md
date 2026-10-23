# Request Lifecycle & Sequence Diagrams

This document formalizes the runtime behavior of PolicyFlow across five critical lifecycle scenarios using Mermaid sequence diagrams.

---

## Scenario A: Successful Request Intake and Issuance

In this happy path, an agent submits a valid intake request. The system calculates the deterministic premium snapshot, saves the request, submits to the mock insurer, and receives immediate confirmation with a unique policy number.

```mermaid
sequenceDiagram
    autonumber
    actor Agent as Travel Agent (Client)
    participant API as PolicyRequestController
    participant Intake as PolicyIntakeService
    participant Pricing as PremiumCalculationService
    participant DB as PostgreSQL Database
    participant Orch as IssuanceOrchestrator
    participant Mock as Mock Insurer (Simulator)

    Agent->>API: POST /api/policy-requests (agent_id, client_request_id, trip dates, plan)
    API->>API: FormRequest Validation (dates, age, plan)
    API->>Intake: processIntake(payload)
    
    rect rgb(240, 248, 255)
        note over Intake,DB: Atomic Database Transaction
        Intake->>DB: Check (agent_id, client_request_id)
        DB-->>Intake: Not found
        Intake->>Pricing: calculate(plan, age, destination, sum_insured, days)
        Pricing-->>Intake: Snapshot (base_rate, multipliers, final_premium)
        Intake->>DB: INSERT into policy_requests (status=VALIDATED)
        Intake->>DB: INSERT into policy_state_history (from=RECEIVED, to=VALIDATED)
    end
    
    API-->>Agent: HTTP 201 Created (id, status=VALIDATED, premium snapshot)

    Agent->>API: POST /api/policy-requests/{id}/submit
    API->>Orch: submitForIssuance(policyRequestId)
    Orch->>DB: LOCK policy_requests WHERE id = {id} FOR UPDATE
    Orch->>DB: INSERT into issuance_attempts (attempt_number=1, status=IN_FLIGHT)
    Orch->>DB: UPDATE policy_requests SET status = SUBMITTED
    
    Orch->>Mock: POST /api/mock-insurer/issue (payload, scenario=SUCCESS)
    Mock-->>Orch: HTTP 200 OK (policy_number="POL-2026-X", provider_ref="PRV-9871")
    
    rect rgb(240, 255, 240)
        note over Orch,DB: Finalize Issuance
        Orch->>DB: UPDATE issuance_attempts SET status=SUCCESS, response_code=200
        Orch->>DB: UPDATE policy_requests SET status=ISSUED, policy_number="POL-2026-X"
        Orch->>DB: INSERT into policy_state_history (from=SUBMITTED, to=ISSUED)
    end
    
    API-->>Agent: HTTP 200 OK (status=ISSUED, policy_number="POL-2026-X")
```

---

## Scenario B: Duplicate Request Submission (Idempotent Intake)

When an agent retries an intake request (e.g., due to an initial client network disconnection or aggressive client retry) with identical `(agent_id, client_request_id)`, the system safely detects the record and returns HTTP 200 without creating duplicate records or recalculating pricing.

```mermaid
sequenceDiagram
    autonumber
    actor Agent as Travel Agent (Client)
    participant API as PolicyRequestController
    participant Intake as PolicyIntakeService
    participant DB as PostgreSQL Database

    Agent->>API: POST /api/policy-requests (agent_id="AGENT-17", client_request_id="AGT-2026-0001")
    API->>API: FormRequest Validation
    API->>Intake: processIntake(payload)
    
    rect rgb(255, 250, 240)
        note over Intake,DB: Idempotency Verification
        Intake->>DB: SELECT * FROM policy_requests WHERE agent_id="AGENT-17" AND client_request_id="AGT-2026-0001"
        DB-->>Intake: Found existing PolicyRequest #14 (status=VALIDATED, premium=225.00)
    end
    
    Intake-->>API: Existing PolicyRequest #14 (is_duplicate=true)
    API-->>Agent: HTTP 200 OK (id=14, status=VALIDATED, premium=225.00, message="Existing request safely returned")
```

---

## Scenario C: Provider Timeout After Successful Issuance

A notorious distributed systems hazard: the client submits a policy to the insurer, the insurer successfully creates the policy internally, but the HTTP connection drops or times out before returning the response. **PolicyFlow avoids double issuance by never blindly reissuing.**

```mermaid
sequenceDiagram
    autonumber
    participant Orch as IssuanceOrchestrator
    participant DB as PostgreSQL Database
    participant Mock as Mock Insurer (External API)
    participant Recon as ReconciliationService

    Orch->>DB: UPDATE policy_requests SET status = SUBMITTED
    Orch->>DB: INSERT into issuance_attempts (attempt_number=1, status=IN_FLIGHT)
    Orch->>Mock: POST /api/mock-insurer/issue (scenario=TIMEOUT_AFTER_ISSUE)
    
    note over Mock: Mock Insurer creates policy internally in mock_insurer_policies table
    note over Mock,Orch: Network Socket Timeout / Gateway 504
    Mock--xOrch: Read Timeout (cURL error 28: Connection timed out)
    
    rect rgb(255, 240, 245)
        note over Orch,DB: Defensive Failure Handling
        Orch->>DB: UPDATE issuance_attempts SET status=TIMEOUT, error_detail="cURL 28 timeout"
        Orch->>DB: UPDATE policy_requests SET status=PENDING_CONFIRMATION
        Orch->>DB: INSERT into policy_state_history (from=SUBMITTED, to=PENDING_CONFIRMATION)
    end

    note over Recon: Reconciliation triggers (via Schedule or Manual Call)
    Recon->>DB: SELECT * FROM policy_requests WHERE status='PENDING_CONFIRMATION'
    DB-->>Recon: PolicyRequest (id=14, client_request_id="AGT-2026-0001")
    
    Recon->>Mock: GET /api/mock-insurer/status?client_request_id="AGT-2026-0001"
    Mock-->>Recon: HTTP 200 OK (status="ISSUED", policy_number="POL-MOCK-99", provider_ref="PRV-TIMEOUT-1")
    
    rect rgb(240, 255, 240)
        note over Recon,DB: Reconciled to Truth
        Recon->>DB: UPDATE policy_requests SET status='ISSUED', policy_number="POL-MOCK-99", provider_reference="PRV-TIMEOUT-1"
        Recon->>DB: INSERT into policy_state_history (from=PENDING_CONFIRMATION, to=ISSUED, reason="Reconciled via provider query")
    end
```

---

## Scenario D: Callback Delivery and Deduplication

When policies are processed asynchronously by the provider, the provider delivers events via webhooks. The callback endpoint logs the raw event and prevents duplicate processing if the provider sends the same event multiple times.

```mermaid
sequenceDiagram
    autonumber
    participant Provider as External Mock Insurer
    participant Webhook as MockInsurerCallbackController
    participant Service as ProviderCallbackService
    participant DB as PostgreSQL Database

    Provider->>Webhook: POST /api/mock-insurer/callback (event_id="EVT-100", policy_id=14, event_type="POLICY_ISSUED", policy_number="POL-999")
    Webhook->>Service: handleCallback(payload)
    
    rect rgb(240, 248, 255)
        note over Service,DB: Event Storage & Deduplication
        Service->>DB: INSERT INTO provider_events (provider, event_id, payload) ON CONFLICT DO NOTHING
        DB-->>Service: Inserted (rows_affected = 1)
        Service->>DB: LOCK policy_requests WHERE id=14 FOR UPDATE
        Service->>DB: UPDATE policy_requests SET status='ISSUED', policy_number='POL-999' WHERE status != 'ISSUED'
        Service->>DB: INSERT into policy_state_history (from=SUBMITTED, to=ISSUED)
    end
    Webhook-->>Provider: HTTP 200 OK (acknowledged=true)

    note over Provider,Webhook: Provider re-delivers identical event 20 seconds later
    Provider->>Webhook: POST /api/mock-insurer/callback (event_id="EVT-100", policy_id=14, ...)
    Webhook->>Service: handleCallback(payload)
    
    rect rgb(255, 250, 240)
        Service->>DB: INSERT INTO provider_events (provider, event_id, payload) ON CONFLICT DO NOTHING
        DB-->>Service: Duplicate key violation caught or rows_affected = 0
        note over Service: Duplicate event detected. Skip state machine mutation.
    end
    Webhook-->>Provider: HTTP 200 OK (acknowledged=true, note="duplicate_ignored")
```

---

## Scenario E: Queue Job Failure and Reconciliation

Demonstrating resilient asynchronous execution when using Redis queues.

```mermaid
sequenceDiagram
    autonumber
    participant App as Laravel API
    participant Redis as Redis Queue (default)
    participant Worker as Artisan Queue Worker
    participant DB as PostgreSQL Database
    participant Mock as Mock Insurer
    participant Sched as Reconciliation Scheduler

    App->>DB: Policy created in DB
    App->>Redis: Dispatch SubmitPolicyForIssuance(id=14) afterCommit
    Worker->>Redis: Pop SubmitPolicyForIssuance(id=14)
    Worker->>Mock: POST /api/mock-insurer/issue (scenario=TEMPORARY_FAILURE)
    Mock-->>Worker: HTTP 503 Service Unavailable
    
    Worker->>DB: INSERT into issuance_attempts (attempt_number=1, status=FAILED, error="503")
    note over Worker: Job throws RetriableException (releases back to Redis with 30s delay)
    
    Worker->>Redis: Release job (attempt 2 of 3)
    note over Worker: If all 3 attempts exhausted:
    Worker->>DB: UPDATE policy_requests SET status='NEEDS_RECONCILIATION'
    Worker->>DB: INSERT into failed_jobs table
    
    note over Sched: Scheduled artisan policyflow:reconcile runs
    Sched->>DB: Query requests in NEEDS_RECONCILIATION
    Sched->>Mock: Status query or safe retry
    Sched->>DB: Updates policy according to authoritative provider state
```
