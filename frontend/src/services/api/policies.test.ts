import { describe, expect, it, vi, beforeEach } from 'vitest';
import { fetchPolicies, createPolicyRequest, submitPolicyForIssuance } from './policies';

describe('Policies API Service', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('fetchPolicies builds correct query string parameters', async () => {
    const mockResponse = {
      data: [],
      meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    };

    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce({
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => mockResponse,
    } as Response);

    const result = await fetchPolicies({
      status: 'ISSUED',
      agent_id: 'AGENT-01',
      destination: 'INTERNATIONAL',
      plan_code: 'PLUS',
      page: 2,
      per_page: 10,
    });

    expect(fetchSpy).toHaveBeenCalled();
    const calledUrl = fetchSpy.mock.calls[0][0] as string;
    expect(calledUrl).toContain('status=ISSUED');
    expect(calledUrl).toContain('agent_id=AGENT-01');
    expect(calledUrl).toContain('destination=INTERNATIONAL');
    expect(calledUrl).toContain('plan_code=PLUS');
    expect(calledUrl).toContain('page=2');
    expect(calledUrl).toContain('per_page=10');
    expect(result).toEqual(mockResponse);
  });

  it('createPolicyRequest posts structured payload with JSON headers', async () => {
    const payload = {
      client_request_id: 'AGT-TEST-001',
      agent_id: 'AGENT-01',
      customer_name: 'Test Customer',
      date_of_birth: '1995-05-15',
      destination: 'INTERNATIONAL' as const,
      trip_start: '2026-12-01',
      trip_end: '2026-12-05',
      plan_code: 'PLUS' as const,
      sum_insured: 300000,
    };

    const mockResponse = {
      data: { id: 1, ...payload, status: 'RECEIVED', final_premium: '225.00' },
      meta: { idempotent_replay: false },
    };

    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce({
      ok: true,
      status: 201,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => mockResponse,
    } as Response);

    const result = await createPolicyRequest(payload);

    expect(fetchSpy).toHaveBeenCalled();
    const callOptions = fetchSpy.mock.calls[0][1] as RequestInit;
    expect(callOptions.method).toBe('POST');
    expect(JSON.parse(callOptions.body as string)).toEqual(payload);
    expect(result.data.final_premium).toBe('225.00');
  });

  it('submitPolicyForIssuance attaches X-Mock-Scenario header', async () => {
    const mockResult = {
      success: true,
      status: 'ISSUED',
      policy_id: 1,
      policy_number: 'POL-MOCK-999',
    };

    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce({
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => mockResult,
    } as Response);

    const res = await submitPolicyForIssuance(1, 'TIMEOUT_AFTER_ISSUE');

    expect(fetchSpy).toHaveBeenCalled();
    const callOptions = fetchSpy.mock.calls[0][1] as RequestInit;
    const headers = callOptions.headers as Record<string, string>;
    expect(headers['X-Mock-Scenario']).toBe('TIMEOUT_AFTER_ISSUE');
    expect(res.status).toBe('ISSUED');
  });
});
