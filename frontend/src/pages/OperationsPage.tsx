import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  RefreshCw,
  Play,
  Clock,
  AlertTriangle,
  ArrowRight,
  ShieldCheck,
  RotateCcw,
} from 'lucide-react';
import { PageHeader } from '../components/layout/PageHeader';
import { Button } from '../components/ui/Button';
import { StatusBadge } from '../components/ui/StatusBadge';
import { LoadingState } from '../components/ui/LoadingState';
import { ErrorState } from '../components/ui/ErrorState';
import { EmptyState } from '../components/ui/EmptyState';
import { fetchPolicies } from '../services/api/policies';
import { runReconciliation } from '../services/api/operations';
import type { PolicyRequest, ReconciliationResult } from '../types';

export const OperationsPage: React.FC = () => {
  const navigate = useNavigate();

  const [pendingPolicies, setPendingPolicies] = useState<PolicyRequest[]>([]);
  const [failedPolicies, setFailedPolicies] = useState<PolicyRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Reconciliation Execution State
  const [reconciling, setReconciling] = useState(false);
  const [lastReconciliation, setLastReconciliation] = useState<ReconciliationResult | null>(null);
  const [reconciliationMessage, setReconciliationMessage] = useState<string | null>(null);

  const loadOperationalData = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);

    try {
      const [pendingRes, failedRes] = await Promise.all([
        fetchPolicies({ status: 'PENDING_CONFIRMATION', per_page: 50 }),
        fetchPolicies({ status: 'FAILED', per_page: 50 }),
      ]);

      setPendingPolicies(pendingRes.data);
      setFailedPolicies(failedRes.data);
    } catch (err: unknown) {
      const msg = (err as { message?: string })?.message || 'Failed to communicate with operations API.';
      setError(msg);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadOperationalData();
  }, [loadOperationalData]);

  const handleTriggerReconciliation = async () => {
    setReconciling(true);
    setReconciliationMessage(null);

    try {
      const result = await runReconciliation(100);
      setLastReconciliation(result.data);
      setReconciliationMessage(result.message);
      // Reload queue lists
      await loadOperationalData(true);
    } catch (err: unknown) {
      const msg = (err as { message?: string })?.message || 'Reconciliation execution failed.';
      setError(msg);
    } finally {
      setReconciling(false);
    }
  };

  if (loading) {
    return (
      <>
        <PageHeader title="Operations & Reconciliation" subtitle="Evaluating pending queue..." />
        <div className="page-body">
          <LoadingState message="Querying pending confirmation and failed queues..." rows={4} />
        </div>
      </>
    );
  }

  if (error) {
    return (
      <>
        <PageHeader title="Operations & Reconciliation" subtitle="Operations Error" />
        <div className="page-body">
          <ErrorState
            title="Operations Console Unavailable"
            message={error}
            onRetry={() => loadOperationalData(false)}
          />
        </div>
      </>
    );
  }

  const totalActionable = pendingPolicies.length + failedPolicies.length;

  return (
    <>
      <PageHeader
        title="Operations & Reconciliation"
        subtitle="Resolve ambiguous issuance timeouts and inspect retryable provider failures"
        actions={
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <Button
              variant="secondary"
              size="sm"
              icon={<RefreshCw size={14} className={refreshing ? 'spinner' : ''} />}
              onClick={() => loadOperationalData(true)}
              disabled={refreshing || reconciling}
            >
              {refreshing ? 'Refreshing...' : 'Refresh'}
            </Button>
            <Button
              variant="primary"
              size="sm"
              loading={reconciling}
              icon={<Play size={14} />}
              onClick={handleTriggerReconciliation}
            >
              {reconciling ? 'Running Reconciliation...' : 'Run Reconciliation Pass'}
            </Button>
          </div>
        }
      />

      <div className="page-body">
        {/* Reconciliation Outcome Report */}
        {lastReconciliation && (
          <div
            style={{
              marginBottom: '2rem',
              padding: '1.25rem 1.5rem',
              borderRadius: 'var(--radius-lg)',
              backgroundColor: 'var(--color-surface)',
              border: '1px solid var(--color-border)',
              boxShadow: 'var(--shadow-sm)',
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '1rem' }}>
              <ShieldCheck size={22} style={{ color: 'var(--color-success)' }} />
              <div>
                <h4 style={{ fontSize: '0.9375rem', fontWeight: 600, color: 'var(--color-text-primary)' }}>
                  Reconciliation Execution Completed
                </h4>
                <p style={{ fontSize: '0.75rem', color: 'var(--color-text-secondary)' }}>
                  {reconciliationMessage}
                </p>
              </div>
            </div>

            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))',
                gap: '1rem',
                backgroundColor: 'var(--color-surface-subtle)',
                padding: '1rem',
                borderRadius: 'var(--radius-md)',
              }}
            >
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--color-text-secondary)', textTransform: 'uppercase' }}>
                  Examined
                </div>
                <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--color-text-primary)' }}>
                  {lastReconciliation.examined}
                </div>
              </div>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--color-success-text)', textTransform: 'uppercase' }}>
                  Reconciled to ISSUED
                </div>
                <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--color-success)' }}>
                  {lastReconciliation.reconciled}
                </div>
              </div>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--color-warning-text)', textTransform: 'uppercase' }}>
                  Still Pending
                </div>
                <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--color-warning)' }}>
                  {lastReconciliation.still_pending}
                </div>
              </div>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--color-primary)', textTransform: 'uppercase' }}>
                  Marked Safe to Retry
                </div>
                <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--color-primary)' }}>
                  {lastReconciliation.safe_to_retry}
                </div>
              </div>
            </div>
          </div>
        )}

        {totalActionable === 0 ? (
          <div className="card">
            <EmptyState
              title="All Operational Queues Nominal"
              description="Zero policies are currently pending confirmation or marked as failed. All intake submissions are either fully issued or proceeding normally."
              icon={<ShieldCheck size={48} style={{ color: 'var(--color-success)' }} />}
              actionText="View All Policies"
              onAction={() => navigate('/policies')}
            />
          </div>
        ) : (
          <>
            {/* 1. Pending Confirmation Queue */}
            {pendingPolicies.length > 0 && (
              <div className="card" style={{ marginBottom: '2rem' }}>
                <div className="card-header" style={{ borderLeft: '4px solid var(--color-warning)' }}>
                  <div>
                    <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <Clock size={16} style={{ color: 'var(--color-warning)' }} />
                      <span>Pending Confirmation Queue ({pendingPolicies.length})</span>
                    </h3>
                    <p className="card-description">
                      These policies encountered provider socket timeouts or were accepted asynchronously.
                      Reconciliation matches them against authoritative provider policy records.
                    </p>
                  </div>
                  <Button
                    variant="primary"
                    size="sm"
                    loading={reconciling}
                    onClick={handleTriggerReconciliation}
                  >
                    Reconcile Now
                  </Button>
                </div>

                <div className="card-body" style={{ padding: 0 }}>
                  <div className="table-responsive" style={{ border: 'none' }}>
                    <table className="data-table">
                      <thead>
                        <tr>
                          <th>Request ID</th>
                          <th>Customer</th>
                          <th>Agent</th>
                          <th>Destination</th>
                          <th>Premium</th>
                          <th>Status</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        {pendingPolicies.map((p) => (
                          <tr key={p.id}>
                            <td>
                              <code style={{ fontSize: '0.8125rem' }}>{p.client_request_id}</code>
                            </td>
                            <td>{p.customer_name}</td>
                            <td>{p.agent_id}</td>
                            <td>{p.destination}</td>
                            <td>₹{p.final_premium}</td>
                            <td><StatusBadge status={p.status} size="sm" /></td>
                            <td>
                              <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => navigate(`/policies/${p.id}`)}
                              >
                                Investigate <ArrowRight size={12} />
                              </Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            )}

            {/* 2. Failed / Retryable Queue */}
            {failedPolicies.length > 0 && (
              <div className="card">
                <div className="card-header" style={{ borderLeft: '4px solid var(--color-danger)' }}>
                  <div>
                    <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <AlertTriangle size={16} style={{ color: 'var(--color-danger)' }} />
                      <span>Failed / Retryable Queue ({failedPolicies.length})</span>
                    </h3>
                    <p className="card-description">
                      Provider returned 503 gateway failure or was definitively confirmed unissued.
                      Safe retry is permitted by state machine matrix.
                    </p>
                  </div>
                </div>

                <div className="card-body" style={{ padding: 0 }}>
                  <div className="table-responsive" style={{ border: 'none' }}>
                    <table className="data-table">
                      <thead>
                        <tr>
                          <th>Request ID</th>
                          <th>Customer</th>
                          <th>Agent</th>
                          <th>Attempts</th>
                          <th>Premium</th>
                          <th>Status</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        {failedPolicies.map((p) => (
                          <tr key={p.id}>
                            <td>
                              <code style={{ fontSize: '0.8125rem' }}>{p.client_request_id}</code>
                            </td>
                            <td>{p.customer_name}</td>
                            <td>{p.agent_id}</td>
                            <td>{p.travel_days}</td>
                            <td>₹{p.final_premium}</td>
                            <td><StatusBadge status={p.status} size="sm" /></td>
                            <td>
                              <Button
                                variant="secondary"
                                size="sm"
                                icon={<RotateCcw size={12} />}
                                onClick={() => navigate(`/policies/${p.id}`)}
                              >
                                Safe Retry
                              </Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </>
  );
};
