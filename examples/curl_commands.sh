#!/usr/bin/env bash
# PolicyFlow Example API Interactions & Diagnostic cURL Commands

BASE_URL="http://localhost:8000/api"

echo "=== 1. Submit Initial Valid Policy Request (HTTP 201 Created) ==="
curl -s -X POST "${BASE_URL}/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @examples/intake_request_valid.json | jq .

echo -e "\n\n=== 2. Re-submit Duplicate Request (Testing Idempotency - Returns HTTP 200 OK) ==="
curl -s -X POST "${BASE_URL}/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @examples/intake_request_valid.json | jq .

echo -e "\n\n=== 3. Query Single Policy Request by ID (HTTP 200 OK) ==="
curl -s -X GET "${BASE_URL}/policy-requests/1" \
  -H "Accept: application/json" | jq .

echo -e "\n\n=== 4. List and Filter Policy Requests with Pagination (HTTP 200 OK) ==="
curl -s -X GET "${BASE_URL}/policy-requests?destination=INTERNATIONAL&plan_code=PLUS&per_page=10" \
  -H "Accept: application/json" | jq .

echo -e "\n\n=== 5. Trigger Policy Issuance (Phase 3 - Future) ==="
curl -s -X POST "${BASE_URL}/policy-requests/1/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Mock-Scenario: SUCCESS" | jq .
