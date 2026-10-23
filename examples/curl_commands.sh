#!/usr/bin/env bash
# PolicyFlow Example API Interactions & Diagnostic cURL Commands

BASE_URL="http://localhost:8000/api"

echo "=== 1. Submit Initial Valid Policy Request ==="
curl -s -X POST "${BASE_URL}/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @examples/intake_request_valid.json | jq .

echo -e "\n\n=== 2. Re-submit Duplicate Request (Testing Idempotency - Returns HTTP 200) ==="
curl -s -X POST "${BASE_URL}/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @examples/intake_request_valid.json | jq .

echo -e "\n\n=== 3. Query Policy Status & Audit Trail by ID ==="
curl -s -X GET "${BASE_URL}/policy-requests/1" \
  -H "Accept: application/json" | jq .

echo -e "\n\n=== 4. Trigger Policy Issuance with Scenario Override (e.g. TIMEOUT_AFTER_ISSUE) ==="
curl -s -X POST "${BASE_URL}/policy-requests/1/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Mock-Scenario: TIMEOUT_AFTER_ISSUE" | jq .

echo -e "\n\n=== 5. Ingest Webhook Event from Mock Insurer ==="
curl -s -X POST "${BASE_URL}/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @examples/callback_success.json | jq .
