/**
 * PolicyFlow Operations UI — Authoritative Domain Contracts
 */

export type PolicyStatus =
  | 'RECEIVED'
  | 'VALIDATED'
  | 'SUBMITTED'
  | 'PENDING_CONFIRMATION'
  | 'ISSUED'
  | 'FAILED'
  | 'NEEDS_RECONCILIATION';

export type PlanCode = 'BASIC' | 'PLUS' | 'PREMIUM';

export type Destination = 'DOMESTIC' | 'INTERNATIONAL';

export interface StorePolicyRequestPayload {
  client_request_id: string;
  agent_id: string;
  customer_name: string;
  date_of_birth: string; // YYYY-MM-DD
  destination: Destination;
  trip_start: string;    // YYYY-MM-DD
  trip_end: string;      // YYYY-MM-DD
  plan_code: PlanCode;
  sum_insured: number;
}

export interface IssuanceAttempt {
  id: number;
  policy_request_id: number;
  attempt_number: number;
  provider: string;
  scenario_requested?: string;
  request_payload?: Record<string, unknown>;
  response_payload?: Record<string, unknown> | null;
  response_status_code?: number | null;
  status: 'IN_FLIGHT' | 'SUCCESS' | 'TEMPORARY_FAILURE' | 'TIMEOUT' | 'PENDING' | 'FAILED';
  error_detail?: string | null;
  attempted_at: string;
  completed_at?: string | null;
}

export interface PolicyStateHistory {
  id: number;
  policy_request_id: number;
  from_state: PolicyStatus;
  to_state: PolicyStatus;
  trigger: string;
  reason?: string | null;
  created_at: string;
}

export interface ProviderEvent {
  id: number;
  provider: string;
  event_id: string;
  event_type: string;
  policy_request_id?: number | null;
  provider_reference?: string | null;
  payload: Record<string, unknown>;
  processed: boolean;
  processed_at?: string | null;
  created_at: string;
}

export interface PolicyRequest {
  id: number;
  client_request_id: string;
  agent_id: string;
  customer_name: string;
  date_of_birth: string;
  destination: Destination;
  trip_start: string;
  trip_end: string;
  travel_days: number;
  plan_code: PlanCode;
  sum_insured: string | number;
  base_daily_rate: string | number;
  age_multiplier: string | number;
  destination_multiplier: string | number;
  coverage_multiplier: string | number;
  final_premium: string | number;
  status: PolicyStatus;
  policy_number?: string | null;
  provider_reference?: string | null;
  created_at: string;
  updated_at: string;
  attempts?: IssuanceAttempt[];
  state_history?: PolicyStateHistory[];
  provider_events?: ProviderEvent[];
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PolicyListResponse {
  data: PolicyRequest[];
  meta: PaginationMeta;
}

export interface DashboardMetrics {
  total_requests: number;
  issued: number;
  pending_confirmation: number;
  needs_reconciliation: number;
  failed: number;
  submitted: number;
  received: number;
  validated: number;
  total_premium_issued: string;
}

export interface ReconciliationItemResult {
  status: string;
  policy_id?: number;
  policy_number?: string;
  provider_reference?: string;
  message?: string;
}

export interface ReconciliationResult {
  examined: number;
  reconciled: number;
  still_pending: number;
  safe_to_retry: number;
  failed: number;
  items: ReconciliationItemResult[];
}

export interface ApiError {
  code: string;
  message: string;
  status: number;
  fields?: Record<string, string[]>;
}

export interface SubmissionResult {
  success: boolean;
  status: PolicyStatus;
  policy_id: number;
  client_request_id?: string;
  policy_number?: string | null;
  provider_reference?: string | null;
  final_premium?: string | number;
  idempotent_replay?: boolean;
  message?: string;
  error?: string;
  requires_reconciliation?: boolean;
  retriable?: boolean;
}
