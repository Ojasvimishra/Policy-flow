import type {
  PolicyListResponse,
  PolicyRequest,
  StorePolicyRequestPayload,
  SubmissionResult,
} from '../../types';
import { apiClient } from './client';

export interface PolicyFilterParams {
  status?: string;
  agent_id?: string;
  destination?: string;
  plan_code?: string;
  page?: number;
  per_page?: number;
}

export async function fetchPolicies(params: PolicyFilterParams = {}): Promise<PolicyListResponse> {
  const query = new URLSearchParams();

  if (params.status) query.set('status', params.status);
  if (params.agent_id) query.set('agent_id', params.agent_id);
  if (params.destination) query.set('destination', params.destination);
  if (params.plan_code) query.set('plan_code', params.plan_code);
  if (params.page) query.set('page', String(params.page));
  if (params.per_page) query.set('per_page', String(params.per_page));

  const qs = query.toString();
  const endpoint = qs ? `/policy-requests?${qs}` : '/policy-requests';

  return apiClient<PolicyListResponse>(endpoint, { method: 'GET' });
}

export async function fetchPolicyById(id: number | string): Promise<{ data: PolicyRequest; meta?: Record<string, unknown> }> {
  return apiClient<{ data: PolicyRequest; meta?: Record<string, unknown> }>(`/policy-requests/${id}`, {
    method: 'GET',
  });
}

export async function createPolicyRequest(
  payload: StorePolicyRequestPayload
): Promise<{ data: PolicyRequest; meta?: { idempotent_replay?: boolean; payload_mismatch?: boolean } }> {
  return apiClient<{ data: PolicyRequest; meta?: { idempotent_replay?: boolean; payload_mismatch?: boolean } }>(
    '/policy-requests',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    }
  );
}

export async function submitPolicyForIssuance(
  id: number | string,
  scenario?: string
): Promise<SubmissionResult> {
  const headers: Record<string, string> = {};
  if (scenario) {
    headers['X-Mock-Scenario'] = scenario;
  }

  return apiClient<SubmissionResult>(`/policy-requests/${id}/submit`, {
    method: 'POST',
    headers,
    body: JSON.stringify(scenario ? { scenario } : {}),
  });
}
