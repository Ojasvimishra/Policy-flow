import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  ShieldCheck,
  Clock,
  AlertTriangle,
  FileText,
  PlusCircle,
  RefreshCw,
  ArrowRight,
  TrendingUp,
} from 'lucide-react';
import { PageHeader } from '../components/layout/PageHeader';
import { Button } from '../components/ui/Button';
import { StatusBadge } from '../components/ui/StatusBadge';
import { LoadingState } from '../components/ui/LoadingState';
import { ErrorState } from '../components/ui/ErrorState';
import { EmptyState } from '../components/ui/EmptyState';
import { fetchDashboardMetrics } from '../services/api/operations';
import { fetchPolicies } from '../services/api/policies';
import type { DashboardMetrics, PolicyRequest } from '../types';

export const OverviewPage: React.FC = () => {
  const navigate = useNavigate();
  const [metrics, setMetrics] = useState<DashboardMetrics | null>(null);
  const [recentPolicies, setRecentPolicies] = useState<PolicyRequest[]>([]);
  const [attentionPolicies, setAttentionPolicies] = useState<PolicyRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadData = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);

    try {
      const [metricsRes, policiesRes, attentionRes] = await Promise.all([
        fetchDashboardMetrics(),
        fetchPolicies({ per_page: 5, page: 1 }),
        fetchPolicies({ status: 'PENDING_CONFIRMATION', per_page: 5, page: 1 }),
      ]);

      setMetrics(metricsRes.data);
      setRecentPolicies(policiesRes.data);
      setAttentionPolicies(attentionRes.data);
    } catch (err: unknown) {
      const msg = (err as { message?: string })?.message || 'Failed to communicate with PolicyFlow API.';
      setError(msg);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  if (loading) {
    return (
      <>
        <PageHeader title="Overview" subtitle="Operational monitoring & request pipeline" />
        <div className="page-body">
          <LoadingState message="Fetching live system metrics from primary database..." rows={4} />
        </div>
      </>
    );
  }

  if (error) {
    return (
      <>
        <PageHeader title="Overview" subtitle="Operational monitoring & request pipeline" />
        <div className="page-body">
          <ErrorState
            title="Dashboard Unavailable"
            message={error}
            onRetry={() => loadData(false)}
          />
        </div>
      </>
    );
  }

  const isEmpty = (metrics?.total_requests ?? 0) === 0 && recentPolicies.length === 0;

  return (
    <>
      <PageHeader
        title="Overview"
        subtitle="Operational monitoring, policy issuance pipeline, and reconciliation queue"
        actions={
          <>
            <Button
              variant="secondary"
              size="sm"
              icon={<RefreshCw size={14} className={refreshing ? 'spinner' : ''} />}
              onClick={() => loadData(true)}
              disabled={refreshing}
            >
              {refreshing ? 'Refreshing...' : 'Refresh'}
            </Button>
            <Button
              variant="primary"
              size="sm"
              icon={<PlusCircle size={15} />}
              onClick={() => navigate('/policies/new')}
            >
              New Policy
            </Button>
          </>
        }
      />

      <div className="page-body">
        {isEmpty ? (
          <EmptyState
            title="No Policy Requests Found"
            description="The database currently contains no policy requests. Submit your first customer request to initialize the issuance workflow."
            actionText="Create First Policy"
            onAction={() => navigate('/policies/new')}
          />
        ) : (
          <>
            {/* Real Metrics Cards */}
            <div className="metrics-grid">
              <div className="metric-card primary">
                <div className="metric-label">
                  <span>Total Intake Requests</span>
                  <FileText size={16} />
                </div>
                <div className="metric-value">{metrics?.total_requests ?? 0}</div>
                <div className="metric-subtext">Authoritative database record count</div>
              </div>

              <div className="metric-card success">
                <div className="metric-label">
                  <span>Confirmed Issued</span>
                  <ShieldCheck size={16} />
                </div>
                <div className="metric-value">{metrics?.issued ?? 0}</div>
                <div className="metric-subtext">Terminal immutable policy contracts</div>
              </div>

              <div className="metric-card warning">
                <div className="metric-label">
                  <span>Pending Confirmation</span>
                  <Clock size={16} />
                </div>
                <div className="metric-value">{metrics?.pending_confirmation ?? 0}</div>
                <div className="metric-subtext">Awaiting webhook or reconciliation</div>
              </div>

              <div className="metric-card danger">
                <div className="metric-label">
                  <span>Failed / Stale</span>
                  <AlertTriangle size={16} />
                </div>
                <div className="metric-value">
                  {(metrics?.failed ?? 0) + (metrics?.needs_reconciliation ?? 0)}
                </div>
                <div className="metric-subtext">Retryable errors or ambiguous state</div>
              </div>
            </div>

            {/* Total Issued Financials Banner */}
            {metrics && parseFloat(metrics.total_premium_issued) > 0 && (
              <div
                style={{
                  marginBottom: '2rem',
                  padding: '1rem 1.5rem',
                  backgroundColor: 'var(--color-surface)',
                  border: '1px solid var(--color-border)',
                  borderRadius: 'var(--radius-lg)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  boxShadow: 'var(--shadow-sm)',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                  <div
                    style={{
                      width: '36px',
                      height: '36px',
                      borderRadius: 'var(--radius-md)',
                      backgroundColor: 'var(--color-primary-light)',
                      color: 'var(--color-primary)',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                    }}
                  >
                    <TrendingUp size={20} />
                  </div>
                  <div>
                    <div style={{ fontSize: '0.8125rem', fontWeight: 600, color: 'var(--color-text-primary)' }}>
                      Cumulative Premium Underwritten
                    </div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--color-text-secondary)' }}>
                      Aggregated across all confirmed ISSUED policies
                    </div>
                  </div>
                </div>
                <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--color-text-primary)', fontVariantNumeric: 'tabular-nums' }}>
                  ₹{metrics.total_premium_issued}
                </div>
              </div>
            )}

            {/* Operational Attention Queue */}
            {attentionPolicies.length > 0 && (
              <div className="card" style={{ marginBottom: '2rem', borderLeft: '4px solid var(--color-warning)' }}>
                <div className="card-header">
                  <div>
                    <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: 'var(--color-warning-text)' }}>
                      <AlertTriangle size={18} />
                      <span>Operational Attention: Pending Confirmation Queue ({attentionPolicies.length})</span>
                    </h3>
                    <p className="card-description">
                      These requests encountered uncertain timeouts or are waiting for external provider confirmation.
                    </p>
                  </div>
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => navigate('/operations')}
                  >
                    View Operations
                  </Button>
                </div>
                <div className="card-body" style={{ padding: 0 }}>
                  <div className="table-responsive" style={{ border: 'none' }}>
                    <table className="data-table">
                      <thead>
                        <tr>
                          <th>Client Request ID</th>
                          <th>Customer</th>
                          <th>Agent</th>
                          <th>Status</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        {attentionPolicies.map((p) => (
                          <tr key={p.id}>
                            <td>
                              <code style={{ fontSize: '0.8125rem' }}>{p.client_request_id}</code>
                            </td>
                            <td>{p.customer_name}</td>
                            <td>{p.agent_id}</td>
                            <td><StatusBadge status={p.status} size="sm" /></td>
                            <td>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => navigate(`/policies/${p.id}`)}
                              >
                                Investigate <ArrowRight size={13} />
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

            {/* Recent Requests Section */}
            <div className="card">
              <div className="card-header">
                <div>
                  <h3 className="card-title">Recent Policy Requests</h3>
                  <p className="card-description">Latest transactions received across all agency partners</p>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => navigate('/policies')}
                >
                  View All Policies <ArrowRight size={14} />
                </Button>
              </div>

              <div className="card-body" style={{ padding: 0 }}>
                <div className="table-responsive" style={{ border: 'none' }}>
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th>Request ID</th>
                        <th>Customer</th>
                        <th>Destination</th>
                        <th>Plan</th>
                        <th className="num-cell">Premium</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {recentPolicies.map((p) => (
                        <tr key={p.id}>
                          <td>
                            <code style={{ fontSize: '0.8125rem' }}>{p.client_request_id}</code>
                          </td>
                          <td style={{ fontWeight: 500 }}>{p.customer_name}</td>
                          <td>
                            <span style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--color-text-secondary)' }}>
                              {p.destination}
                            </span>
                          </td>
                          <td>
                            <span style={{ fontSize: '0.8125rem', fontWeight: 600 }}>{p.plan_code}</span>
                          </td>
                          <td className="num-cell">
                            <strong>₹{p.final_premium}</strong>
                          </td>
                          <td>
                            <StatusBadge status={p.status} size="sm" />
                          </td>
                          <td style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)' }}>
                            {new Date(p.created_at).toLocaleDateString()}
                          </td>
                          <td>
                            <Button
                              variant="secondary"
                              size="sm"
                              onClick={() => navigate(`/policies/${p.id}`)}
                            >
                              Details
                            </Button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </>
        )}
      </div>
    </>
  );
};
