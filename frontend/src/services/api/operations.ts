import type { DashboardMetrics, ReconciliationResult } from '../../types';
import { apiClient } from './client';

export async function fetchDashboardMetrics(): Promise<{ data: DashboardMetrics; meta: { calculated_at: string } }> {
  return apiClient<{ data: DashboardMetrics; meta: { calculated_at: string } }>('/dashboard/metrics', {
    method: 'GET',
  });
}

export async function runReconciliation(
  limit: number = 100
): Promise<{ success: boolean; data: ReconciliationResult; message: string }> {
  return apiClient<{ success: boolean; data: ReconciliationResult; message: string }>('/operations/reconcile', {
    method: 'POST',
    body: JSON.stringify({ limit }),
  });
}
