#!/usr/bin/env bash
set -e

DB_FILE="/tmp/policyflow_test_schema.sqlite"
rm -f "$DB_FILE"

echo "=========================================================="
echo " PolicyFlow - Phase 1 Schema & Constraints Live Verification"
echo "=========================================================="

# 1. Run Schema Creation
echo "[*] Step 1: Executing DDL migrations..."
sqlite3 "$DB_FILE" < scripts/verify_database_schema.sql
echo "  [✓] All 5 tables created successfully."

# 2. Test Valid Policy Intake Record
echo "[*] Step 2: Testing valid policy insertion with pricing snapshot..."
sqlite3 "$DB_FILE" << 'EOF'
INSERT INTO policy_requests (
    agent_id, client_request_id, customer_name, date_of_birth, destination,
    trip_start, trip_end, travel_days, plan_code, sum_insured,
    base_daily_rate, age_multiplier, destination_multiplier, coverage_multiplier, final_premium,
    status
) VALUES (
    'AGENT-17', 'AGT-2026-0001', 'Sample Customer', '1998-04-10', 'INTERNATIONAL',
    '2026-11-10', '2026-11-14', 5, 'PLUS', 300000.00,
    20.00, 1.00, 1.80, 1.25, 225.00,
    'VALIDATED'
);
EOF
echo "  [✓] Valid policy inserted (ID 1, status VALIDATED, premium 225.00)."

# 3. Test Duplicate (agent_id, client_request_id) Rejection
echo "[*] Step 3: Testing duplicate (agent_id, client_request_id) constraint rejection..."
if sqlite3 "$DB_FILE" "INSERT INTO policy_requests (agent_id, client_request_id, customer_name, date_of_birth, destination, trip_start, trip_end, travel_days, plan_code, sum_insured, base_daily_rate, age_multiplier, destination_multiplier, coverage_multiplier, final_premium, status) VALUES ('AGENT-17', 'AGT-2026-0001', 'Another Name', '1990-01-01', 'DOMESTIC', '2026-11-10', '2026-11-14', 5, 'BASIC', 100000.00, 12.00, 1.00, 1.00, 1.00, 60.00, 'RECEIVED');" 2>&1 | grep -q "UNIQUE constraint failed"; then
    echo "  [✓] PASS: Duplicate (agent_id, client_request_id) rejected by database."
else
    echo "  [FAIL] Duplicate intake key was not rejected!"
    exit 1
fi

# 4. Test Policy Number Uniqueness
echo "[*] Step 4: Testing global policy_number uniqueness constraint..."
sqlite3 "$DB_FILE" "UPDATE policy_requests SET policy_number = 'POL-2026-001', status = 'ISSUED' WHERE id = 1;"
sqlite3 "$DB_FILE" "INSERT INTO policy_requests (agent_id, client_request_id, customer_name, date_of_birth, destination, trip_start, trip_end, travel_days, plan_code, sum_insured, base_daily_rate, age_multiplier, destination_multiplier, coverage_multiplier, final_premium, status) VALUES ('AGENT-99', 'AGT-2026-0002', 'Customer 2', '1985-05-05', 'DOMESTIC', '2026-12-01', '2026-12-05', 5, 'BASIC', 100000.00, 12.00, 1.00, 1.00, 1.00, 60.00, 'VALIDATED');"

if sqlite3 "$DB_FILE" "UPDATE policy_requests SET policy_number = 'POL-2026-001' WHERE id = 2;" 2>&1 | grep -q "UNIQUE constraint failed"; then
    echo "  [✓] PASS: Duplicate policy_number rejected by database."
else
    echo "  [FAIL] Duplicate policy_number was not rejected!"
    exit 1
fi

# 5. Test Issuance Attempt Sequence Constraint
echo "[*] Step 5: Testing (policy_request_id, attempt_number) uniqueness..."
sqlite3 "$DB_FILE" "INSERT INTO issuance_attempts (policy_request_id, attempt_number, provider, request_payload, status, attempted_at) VALUES (1, 1, 'mock_insurer', '{}', 'IN_FLIGHT', datetime('now'));"
if sqlite3 "$DB_FILE" "INSERT INTO issuance_attempts (policy_request_id, attempt_number, provider, request_payload, status, attempted_at) VALUES (1, 1, 'mock_insurer', '{}', 'IN_FLIGHT', datetime('now'));" 2>&1 | grep -q "UNIQUE constraint failed"; then
    echo "  [✓] PASS: Duplicate attempt_number for same policy rejected by database."
else
    echo "  [FAIL] Duplicate attempt_number was not rejected!"
    exit 1
fi

# 6. Test Provider Event Deduplication Constraint
echo "[*] Step 6: Testing (provider, event_id) webhook deduplication..."
sqlite3 "$DB_FILE" "INSERT INTO provider_events (provider, event_id, event_type, payload) VALUES ('mock_insurer', 'EVT-1001', 'POLICY_ISSUED', '{\"status\":\"ok\"}');"
if sqlite3 "$DB_FILE" "INSERT INTO provider_events (provider, event_id, event_type, payload) VALUES ('mock_insurer', 'EVT-1001', 'POLICY_ISSUED', '{\"status\":\"ok\"}');" 2>&1 | grep -q "UNIQUE constraint failed"; then
    echo "  [✓] PASS: Duplicate (provider, event_id) rejected by database."
else
    echo "  [FAIL] Duplicate webhook event was not rejected!"
    exit 1
fi

# 7. Test Cascade Delete Integrity
echo "[*] Step 7: Testing foreign key CASCADE delete..."
sqlite3 "$DB_FILE" << 'EOF'
PRAGMA foreign_keys = ON;
DELETE FROM policy_requests WHERE id = 1;
EOF
REMAINING_ATTEMPTS=$(sqlite3 "$DB_FILE" "SELECT count(*) FROM issuance_attempts WHERE policy_request_id = 1;")
if [ "$REMAINING_ATTEMPTS" -eq 0 ]; then
    echo "  [✓] PASS: Child attempts automatically cascaded on parent policy deletion."
else
    echo "  [FAIL] Cascade delete failed! Remaining attempts: $REMAINING_ATTEMPTS"
    exit 1
fi

# 8. Test Mock Insurer Policy Uniqueness
echo "[*] Step 8: Testing mock insurer isolated provider uniqueness..."
sqlite3 "$DB_FILE" "INSERT INTO mock_insurer_policies (client_request_id, agent_id, policy_number, provider_reference, status, sum_insured, premium_charged) VALUES ('AGT-EXT-01', 'AGENT-17', 'MOCK-POL-01', 'REF-01', 'ISSUED', 300000.00, 225.00);"
if sqlite3 "$DB_FILE" "INSERT INTO mock_insurer_policies (client_request_id, agent_id, policy_number, provider_reference, status, sum_insured, premium_charged) VALUES ('AGT-EXT-01', 'AGENT-17', 'MOCK-POL-02', 'REF-02', 'ISSUED', 300000.00, 225.00);" 2>&1 | grep -q "UNIQUE constraint failed"; then
    echo "  [✓] PASS: Duplicate provider policy for same client_request_id rejected."
else
    echo "  [FAIL] Duplicate provider policy was not rejected!"
    exit 1
fi

rm -f "$DB_FILE"

echo -e "\n=========================================================="
echo " All 8 database integrity & constraint tests PASSED cleanly!"
echo "=========================================================="
