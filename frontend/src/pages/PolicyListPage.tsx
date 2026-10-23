import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import {
  PlusCircle,
  RefreshCw,
  Filter,
  X,
  ChevronLeft,
  ChevronRight,
  ArrowRight,
} from 'lucide-react';
import { PageHeader } from '../components/layout/PageHeader';
import { Button } from '../components/ui/Button';
import { StatusBadge } from '../components/ui/StatusBadge';
import { LoadingState } from '../components/ui/LoadingState';
import { ErrorState } from '../components/ui/ErrorState';
import { EmptyState } from '../components/ui/EmptyState';
import { fetchPolicies } from '../services/api/policies';
import type { PaginationMeta, PolicyRequest } from '../types';

export const PolicyListPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();

  const [policies, setPolicies] = useState<PolicyRequest[]>([]);
  const [meta, setMeta] = useState<PaginationMeta>({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
  });

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Read filter query params
  const statusParam = searchParams.get('status') || '';
  const agentParam = searchParams.get('agent_id') || '';
  const destinationParam = searchParams.get('destination') || '';
  const planParam = searchParams.get('plan_code') || '';
  const pageParam = parseInt(searchParams.get('page') || '1', 10);

  const [filterStatus, setFilterStatus] = useState(statusParam);
  const [filterAgent, setFilterAgent] = useState(agentParam);
  const [filterDestination, setFilterDestination] = useState(destinationParam);
  const [filterPlan, setFilterPlan] = useState(planParam);

  const loadPolicies = useCallback(
    async (isRefresh = false) => {
      if (isRefresh) setRefreshing(true);
      else setLoading(true);
      setError(null);

      try {
        const response = await fetchPolicies({
          status: statusParam || undefined,
          agent_id: agentParam || undefined,
          destination: destinationParam || undefined,
          plan_code: planParam || undefined,
          page: pageParam,
          per_page: 15,
        });

        setPolicies(response.data);
        setMeta(response.meta);
      } catch (err: unknown) {
        const msg = (err as { message?: string })?.message || 'Failed to retrieve policy requests from backend.';
        setError(msg);
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [statusParam, agentParam, destinationParam, planParam, pageParam]
  );

  useEffect(() => {
    loadPolicies();
  }, [loadPolicies]);

  const applyFilters = () => {
    const params = new URLSearchParams();
    if (filterStatus) params.set('status', filterStatus);
    if (filterAgent) params.set('agent_id', filterAgent);
    if (filterDestination) params.set('destination', filterDestination);
    if (filterPlan) params.set('plan_code', filterPlan);
    params.set('page', '1'); // reset to page 1 on filter apply
    setSearchParams(params);
  };

  const resetFilters = () => {
    setFilterStatus('');
    setFilterAgent('');
    setFilterDestination('');
    setFilterPlan('');
    setSearchParams(new URLSearchParams());
  };

  const handlePageChange = (newPage: number) => {
    if (newPage < 1 || newPage > meta.last_page) return;
    const params = new URLSearchParams(searchParams);
    params.set('page', String(newPage));
    setSearchParams(params);
  };

  const hasActiveFilters = Boolean(statusParam || agentParam || destinationParam || planParam);

  return (
    <>
      <PageHeader
        title="Policy Requests"
        subtitle="Manage and inspect travel insurance intake requests and lifecycle states"
        actions={
          <>
            <Button
              variant="secondary"
              size="sm"
              icon={<RefreshCw size={14} className={refreshing ? 'spinner' : ''} />}
              onClick={() => loadPolicies(true)}
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
        {/* Filter Toolbar Card */}
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="card-body" style={{ padding: '1.25rem' }}>
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
                gap: '1rem',
                alignItems: 'flex-end',
              }}
            >
              {/* Status Filter */}
              <div>
                <label className="form-label" style={{ marginBottom: '0.35rem', display: 'block' }}>
                  Lifecycle Status
                </label>
                <select
                  className="form-control"
                  value={filterStatus}
                  onChange={(e) => setFilterStatus(e.target.value)}
                >
                  <option value="">All States</option>
                  <option value="RECEIVED">RECEIVED</option>
                  <option value="VALIDATED">VALIDATED</option>
                  <option value="SUBMITTED">SUBMITTED</option>
                  <option value="PENDING_CONFIRMATION">PENDING_CONFIRMATION</option>
                  <option value="ISSUED">ISSUED</option>
                  <option value="FAILED">FAILED</option>
                  <option value="NEEDS_RECONCILIATION">NEEDS_RECONCILIATION</option>
                </select>
              </div>

              {/* Destination Filter */}
              <div>
                <label className="form-label" style={{ marginBottom: '0.35rem', display: 'block' }}>
                  Destination
                </label>
                <select
                  className="form-control"
                  value={filterDestination}
                  onChange={(e) => setFilterDestination(e.target.value)}
                >
                  <option value="">All Destinations</option>
                  <option value="DOMESTIC">DOMESTIC</option>
                  <option value="INTERNATIONAL">INTERNATIONAL</option>
                </select>
              </div>

              {/* Plan Filter */}
              <div>
                <label className="form-label" style={{ marginBottom: '0.35rem', display: 'block' }}>
                  Plan Code
                </label>
                <select
                  className="form-control"
                  value={filterPlan}
                  onChange={(e) => setFilterPlan(e.target.value)}
                >
                  <option value="">All Plans</option>
                  <option value="BASIC">BASIC (₹12)</option>
                  <option value="PLUS">PLUS (₹20)</option>
                  <option value="PREMIUM">PREMIUM (₹30)</option>
                </select>
              </div>

              {/* Agent ID Filter */}
              <div>
                <label className="form-label" style={{ marginBottom: '0.35rem', display: 'block' }}>
                  Partner Agent ID
                </label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="e.g. AGENT-01"
                  value={filterAgent}
                  onChange={(e) => setFilterAgent(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                />
              </div>

              {/* Action Buttons */}
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <Button variant="primary" icon={<Filter size={14} />} onClick={applyFilters}>
                  Apply
                </Button>
                {hasActiveFilters && (
                  <Button variant="ghost" icon={<X size={14} />} onClick={resetFilters}>
                    Reset
                  </Button>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Content Area */}
        {loading ? (
          <div className="card">
            <LoadingState message="Fetching policies from API..." rows={6} />
          </div>
        ) : error ? (
          <div className="card">
            <ErrorState
              title="Unable to Load Policies"
              message={error}
              onRetry={() => loadPolicies(false)}
            />
          </div>
        ) : policies.length === 0 ? (
          <div className="card">
            {hasActiveFilters ? (
              <EmptyState
                title="No Matching Policies"
                description="No policy requests in the system matched your selected filter criteria."
                actionText="Reset All Filters"
                onAction={resetFilters}
              />
            ) : (
              <EmptyState
                title="Policy Repository Empty"
                description="No policy requests currently exist in the database. Use the new policy form to create your first intake request."
                actionText="Create Policy Request"
                onAction={() => navigate('/policies/new')}
              />
            )}
          </div>
        ) : (
          <div className="card">
            <div className="table-responsive">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Request ID</th>
                    <th>Customer Name</th>
                    <th>Agent</th>
                    <th>Destination</th>
                    <th>Plan</th>
                    <th>Trip Duration</th>
                    <th className="num-cell">Premium</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {policies.map((policy) => (
                    <tr key={policy.id}>
                      <td>
                        <code style={{ fontSize: '0.8125rem', fontWeight: 600 }}>
                          {policy.client_request_id}
                        </code>
                        {policy.policy_number && (
                          <div style={{ fontSize: '0.7rem', color: 'var(--color-success-text)' }}>
                            {policy.policy_number}
                          </div>
                        )}
                      </td>
                      <td style={{ fontWeight: 500 }}>{policy.customer_name}</td>
                      <td>
                        <span style={{ fontSize: '0.8125rem', color: 'var(--color-text-secondary)' }}>
                          {policy.agent_id}
                        </span>
                      </td>
                      <td>
                        <span
                          style={{
                            fontSize: '0.75rem',
                            fontWeight: 600,
                            color: policy.destination === 'INTERNATIONAL' ? 'var(--color-primary)' : 'var(--color-text-secondary)',
                          }}
                        >
                          {policy.destination}
                        </span>
                      </td>
                      <td>
                        <span style={{ fontSize: '0.8125rem', fontWeight: 600 }}>{policy.plan_code}</span>
                      </td>
                      <td>
                        <div style={{ fontSize: '0.8125rem' }}>
                          {policy.travel_days} {policy.travel_days === 1 ? 'day' : 'days'}
                        </div>
                        <div style={{ fontSize: '0.7rem', color: 'var(--color-text-muted)' }}>
                          {policy.trip_start} → {policy.trip_end}
                        </div>
                      </td>
                      <td className="num-cell">
                        <strong>₹{policy.final_premium}</strong>
                      </td>
                      <td>
                        <StatusBadge status={policy.status} size="sm" />
                      </td>
                      <td style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)' }}>
                        {new Date(policy.created_at).toLocaleDateString()}
                      </td>
                      <td>
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() => navigate(`/policies/${policy.id}`)}
                        >
                          Details <ArrowRight size={12} />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination Controls */}
            <div
              style={{
                padding: '1rem 1.5rem',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                borderTop: '1px solid var(--color-border)',
                backgroundColor: 'var(--color-surface)',
                fontSize: '0.8125rem',
                color: 'var(--color-text-secondary)',
              }}
            >
              <div>
                Showing page <strong>{meta.current_page}</strong> of <strong>{meta.last_page}</strong> (
                <strong>{meta.total}</strong> total records)
              </div>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <Button
                  variant="secondary"
                  size="sm"
                  icon={<ChevronLeft size={14} />}
                  onClick={() => handlePageChange(meta.current_page - 1)}
                  disabled={meta.current_page <= 1}
                >
                  Previous
                </Button>
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => handlePageChange(meta.current_page + 1)}
                  disabled={meta.current_page >= meta.last_page}
                >
                  <span>Next</span>
                  <ChevronRight size={14} />
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
};
