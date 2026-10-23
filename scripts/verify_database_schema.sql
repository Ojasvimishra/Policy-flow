-- PolicyFlow Phase 1 Database Schema Invariant & Constraint Verification Script
PRAGMA foreign_keys = ON;

-- 1. Create policy_requests table
CREATE TABLE policy_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agent_id VARCHAR(64) NOT NULL,
    client_request_id VARCHAR(128) NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    destination VARCHAR(32) NOT NULL,
    trip_start DATE NOT NULL,
    trip_end DATE NOT NULL,
    travel_days INTEGER NOT NULL,
    plan_code VARCHAR(32) NOT NULL,
    sum_insured DECIMAL(12, 2) NOT NULL,
    base_daily_rate DECIMAL(10, 2) NOT NULL,
    age_multiplier DECIMAL(4, 2) NOT NULL,
    destination_multiplier DECIMAL(4, 2) NOT NULL,
    coverage_multiplier DECIMAL(4, 2) NOT NULL,
    final_premium DECIMAL(10, 2) NOT NULL,
    status VARCHAR(48) NOT NULL DEFAULT 'RECEIVED',
    policy_number VARCHAR(64) NULL UNIQUE,
    provider_reference VARCHAR(128) NULL,
    attempt_count INTEGER NOT NULL DEFAULT 0,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_policy_requests_agent_client_req UNIQUE (agent_id, client_request_id)
);

CREATE INDEX idx_policy_requests_status ON policy_requests(status);
CREATE INDEX idx_policy_requests_agent ON policy_requests(agent_id);

-- 2. Create issuance_attempts table
CREATE TABLE issuance_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    policy_request_id INTEGER NOT NULL REFERENCES policy_requests(id) ON DELETE CASCADE,
    attempt_number INTEGER NOT NULL,
    provider VARCHAR(64) NOT NULL DEFAULT 'mock_insurer',
    scenario_requested VARCHAR(64) NULL,
    request_payload JSON NOT NULL,
    response_status_code INTEGER NULL,
    response_payload JSON NULL,
    error_detail TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'IN_FLIGHT',
    attempted_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_issuance_attempts_policy_attempt UNIQUE (policy_request_id, attempt_number)
);

-- 3. Create provider_events table
CREATE TABLE provider_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider VARCHAR(64) NOT NULL DEFAULT 'mock_insurer',
    event_id VARCHAR(128) NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    policy_request_id INTEGER NULL REFERENCES policy_requests(id) ON DELETE SET NULL,
    provider_reference VARCHAR(128) NULL,
    payload JSON NOT NULL,
    processed BOOLEAN NOT NULL DEFAULT 0,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_provider_events_provider_event UNIQUE (provider, event_id)
);

-- 4. Create policy_state_history table
CREATE TABLE policy_state_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    policy_request_id INTEGER NOT NULL REFERENCES policy_requests(id) ON DELETE CASCADE,
    from_state VARCHAR(48) NOT NULL,
    to_state VARCHAR(48) NOT NULL,
    trigger VARCHAR(64) NOT NULL,
    reason TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_policy_state_history_lookup ON policy_state_history(policy_request_id, created_at);

-- 5. Create mock_insurer_policies table
CREATE TABLE mock_insurer_policies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_request_id VARCHAR(128) NOT NULL UNIQUE,
    agent_id VARCHAR(64) NOT NULL,
    policy_number VARCHAR(64) NOT NULL UNIQUE,
    provider_reference VARCHAR(128) NOT NULL UNIQUE,
    status VARCHAR(32) NOT NULL DEFAULT 'ISSUED',
    sum_insured DECIMAL(12, 2) NOT NULL,
    premium_charged DECIMAL(10, 2) NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
