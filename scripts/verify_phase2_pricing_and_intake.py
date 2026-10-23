#!/usr/bin/env python3
"""
PolicyFlow Phase 2: Pricing Logic, Boundary Cases & Idempotency Verification.
Cross-verifies the exact pricing formula, multiplier tables, and database constraints.
"""
import sys
import sqlite3
import datetime
from decimal import Decimal, ROUND_HALF_UP

print("==========================================================")
print(" PolicyFlow - Phase 2 Pricing & Intake Logic Verification")
print("==========================================================")

BASE_DAILY_RATES = {
    'BASIC': Decimal('12.00'),
    'PLUS': Decimal('20.00'),
    'PREMIUM': Decimal('30.00'),
}

DESTINATION_MULTIPLIERS = {
    'DOMESTIC': Decimal('1.00'),
    'INTERNATIONAL': Decimal('1.80'),
}

COVERAGE_MULTIPLIERS = {
    100000: Decimal('1.00'),
    300000: Decimal('1.25'),
    500000: Decimal('1.50'),
}

def get_age_multiplier(age: int) -> Decimal:
    if age <= 17:
        return Decimal('1.10')
    if age <= 60:
        return Decimal('1.00')
    if age <= 70:
        return Decimal('1.50')
    return Decimal('2.00')

def calculate_age(dob: datetime.date, trip_start: datetime.date) -> int:
    age = trip_start.year - dob.year
    if (trip_start.month, trip_start.day) < (dob.month, dob.day):
        age -= 1
    return age

def calculate_premium(plan: str, sum_insured: int, destination: str, dob_str: str, start_str: str, end_str: str):
    dob = datetime.datetime.strptime(dob_str, "%Y-%m-%d").date()
    start = datetime.datetime.strptime(start_str, "%Y-%m-%d").date()
    end = datetime.datetime.strptime(end_str, "%Y-%m-%d").date()

    if end < start:
        raise ValueError("Trip end date cannot be earlier than trip start date")

    travel_days = (end - start).days + 1
    age = calculate_age(dob, start)
    if age < 0:
        raise ValueError("DOB cannot be after trip start")

    base_rate = BASE_DAILY_RATES[plan]
    dest_mult = DESTINATION_MULTIPLIERS[destination]
    cov_mult = COVERAGE_MULTIPLIERS[sum_insured]
    age_mult = get_age_multiplier(age)

    # Decimal math without float inaccuracies
    raw_premium = base_rate * Decimal(travel_days) * age_mult * dest_mult * cov_mult
    final_premium = raw_premium.quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)

    return {
        'travel_days': travel_days,
        'base_daily_rate': str(base_rate),
        'age_at_trip_start': age,
        'age_multiplier': str(age_mult),
        'destination_multiplier': str(dest_mult),
        'coverage_multiplier': str(cov_mult),
        'final_premium': str(final_premium)
    }

# 1. Authoritative Reference Test
print("[*] Test 1: Verifying Authoritative Reference Calculation (₹225.00)...")
ref = calculate_premium('PLUS', 300000, 'INTERNATIONAL', '1998-04-10', '2026-11-10', '2026-11-14')
assert ref['travel_days'] == 5, f"Expected 5 travel days, got {ref['travel_days']}"
assert ref['base_daily_rate'] == '20.00'
assert ref['age_multiplier'] == '1.00'
assert ref['destination_multiplier'] == '1.80'
assert ref['coverage_multiplier'] == '1.25'
assert ref['final_premium'] == '225.00', f"Expected 225.00, got {ref['final_premium']}"
print(f"  [✓] PASS: 20 * 5 * 1.00 * 1.80 * 1.25 = ₹{ref['final_premium']} (Exact match)")

# 2. Age Boundaries
print("[*] Test 2: Verifying Age Boundaries (17, 18, 60, 61, 70, 71)...")
assert get_age_multiplier(17) == Decimal('1.10')
assert get_age_multiplier(18) == Decimal('1.00')
assert get_age_multiplier(60) == Decimal('1.00')
assert get_age_multiplier(61) == Decimal('1.50')
assert get_age_multiplier(70) == Decimal('1.50')
assert get_age_multiplier(71) == Decimal('2.00')
print("  [✓] PASS: All age multipliers correspond strictly to assignment specifications.")

# 3. Age on Trip Start vs Current Date
print("[*] Test 3: Verifying Age calculated strictly on trip_start...")
dob = datetime.date(2008, 11, 5)
day_before = datetime.date(2026, 11, 4)
birthday = datetime.date(2026, 11, 5)
assert calculate_age(dob, day_before) == 17
assert calculate_age(dob, birthday) == 18
print("  [✓] PASS: Day before 18th birthday is 17; birthday is 18.")

# 4. Same-Day Trip
print("[*] Test 4: Verifying same-day trip inclusive duration (1 day)...")
same_day = calculate_premium('BASIC', 100000, 'DOMESTIC', '1995-01-01', '2026-11-10', '2026-11-10')
assert same_day['travel_days'] == 1
assert same_day['final_premium'] == '12.00'
print("  [✓] PASS: Same-day trip calculated as exactly 1 day (₹12.00).")

# 5. Database Schema & Idempotency Simulation
print("[*] Test 5: Verifying SQLite Database Idempotency & Composite Key Enforcement...")
db = sqlite3.connect(":memory:")
with open("scripts/verify_database_schema.sql") as f:
    db.executescript(f.read())

cursor = db.cursor()

# Insert initial request
cursor.execute("""
INSERT INTO policy_requests (
    agent_id, client_request_id, customer_name, date_of_birth, destination,
    trip_start, trip_end, travel_days, plan_code, sum_insured,
    base_daily_rate, age_multiplier, destination_multiplier, coverage_multiplier, final_premium,
    status
) VALUES (
    'AGENT-17', 'AGT-2026-0001', 'Sample Customer', '1998-04-10', 'INTERNATIONAL',
    '2026-11-10', '2026-11-14', 5, 'PLUS', 300000.00,
    20.00, 1.00, 1.80, 1.25, 225.00,
    'RECEIVED'
);
""")
db.commit()
initial_id = cursor.lastrowid
assert initial_id == 1

# Verify duplicate intake is blocked by database constraint
duplicate_caught = False
try:
    cursor.execute("""
    INSERT INTO policy_requests (
        agent_id, client_request_id, customer_name, date_of_birth, destination,
        trip_start, trip_end, travel_days, plan_code, sum_insured,
        base_daily_rate, age_multiplier, destination_multiplier, coverage_multiplier, final_premium,
        status
    ) VALUES (
        'AGENT-17', 'AGT-2026-0001', 'Sample Customer', '1998-04-10', 'INTERNATIONAL',
        '2026-11-10', '2026-11-14', 5, 'PLUS', 300000.00,
        20.00, 1.00, 1.80, 1.25, 225.00,
        'RECEIVED'
    );
    """)
    db.commit()
except sqlite3.IntegrityError as e:
    duplicate_caught = True
    assert "UNIQUE constraint failed" in str(e)

assert duplicate_caught, "Database UNIQUE constraint failed to trigger on duplicate (agent_id, client_request_id)!"
print("  [✓] PASS: Database rejects duplicate intake key atomically.")

# Verify query for replay
cursor.execute("SELECT id, final_premium, status FROM policy_requests WHERE agent_id = 'AGENT-17' AND client_request_id = 'AGT-2026-0001'")
row = cursor.fetchone()
assert row[0] == 1
assert float(row[1]) == 225.00
assert row[2] == 'RECEIVED'
print("  [✓] PASS: Idempotent replay retrieves canonical record untouched.")

# Verify different agent can use same client_request_id
cursor.execute("""
INSERT INTO policy_requests (
    agent_id, client_request_id, customer_name, date_of_birth, destination,
    trip_start, trip_end, travel_days, plan_code, sum_insured,
    base_daily_rate, age_multiplier, destination_multiplier, coverage_multiplier, final_premium,
    status
) VALUES (
    'AGENT-DIFFERENT', 'AGT-2026-0001', 'Other Customer', '1995-01-01', 'DOMESTIC',
    '2026-11-10', '2026-11-11', 2, 'BASIC', 100000.00,
    12.00, 1.00, 1.00, 1.00, 24.00,
    'RECEIVED'
);
""")
db.commit()
assert cursor.lastrowid == 2
print("  [✓] PASS: Different agent successfully uses same client_request_id (composite key integrity).")

print("\n==========================================================")
print(" All Phase 2 mathematical & database assertions PASSED!")
print("==========================================================")
