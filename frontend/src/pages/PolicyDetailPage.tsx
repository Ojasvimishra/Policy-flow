import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Send,
  RefreshCw,
  ArrowLeft,
  ShieldCheck,
  User,
  Plane,
  AlertTriangle,
  FileCheck,
  CheckCircle2,
} from 'lucide-react';
import { PageHeader } from '../components/layout/PageHeader';
import { Button } from '../components/ui/Button';
import { StatusBadge } from '../components/ui/StatusBadge';
import { LoadingState } from '../components/ui/LoadingState';
import { ErrorState } from '../components/ui/ErrorState';
import { PolicyLifecycleTracker } from '../components/policy/PolicyLifecycleTracker';
import { IssuanceAttemptTimeline } from '../components/policy/IssuanceAttemptTimeline';
import { StateHistoryTimeline } from '../components/policy/StateHistoryTimeline';
import { fetchPolicyById, submitPolicyForIssuance } from '../services/api/policies';
import type { PolicyRequest } from '../types';

export const PolicyDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const [policy, setPolicy] = useState<PolicyRequest | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<{ message: string; status?: number } | null>(null);

  // Submission State
  const [submitting, setSubmitting] = useState(false);
  const [selectedScenario, setSelectedScenario] = useState<string>('SUCCESS');
  const [actionFeedback, setActionFeedback] = useState<{ type: 'success' | 'warning' | 'error'; message: string } | null>(null);

  const loadPolicy = useCallback(
    async (isRefresh = false) => {
      if (!id) return;
      if (isRefresh) setRefreshing(true);
      else setLoading(true);
      setError(null);

      try {
        const response = await fetchPolicyById(id);
        setPolicy(response.data);
      } catch (err: unknown) {
        const errorObj = err as { message?: string; status?: number };
        setError({
          message: errorObj.message || 'Failed to retrieve policy details.',
          status: errorObj.status,
        });
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [id]
  );

  useEffect(() => {
    loadPolicy();
  }, [loadPolicy]);

  const handleSubmitIssuance = async () => {
    if (!id || !policy) return;

    setSubmitting(true);
    setActionFeedback(null);

    try {
      const result = await submitPolicyForIssuance(id, selectedScenario);

      if (result.status === 'ISSUED') {
        setActionFeedback({
          type: 'success',
          message: `Policy successfully issued by insurer! Policy Number: ${result.policy_number || 'ASSIGNED'}`,
        });
      } else if (result.status === 'PENDING_CONFIRMATION') {
        setActionFeedback({
          type: 'warning',
          message: result.error || 'Provider timeout or pending callback. Policy placed in PENDING_CONFIRMATION to prevent blind duplicate issuance.',
        });
      } else {
        setActionFeedback({
          type: 'warning',
          message: result.message || `Submission completed with status: ${result.status}`,
        });
      }

      // Reload fresh policy data to update timelines and badges
      await loadPolicy(true);
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setActionFeedback({
        type: 'error',
        message: errorObj.message || 'Issuance submission failed.',
      });
      await loadPolicy(true);
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <>
        <PageHeader title="Policy Details" subtitle="Loading request record..." />
        <div className="page-body">
          <LoadingState message="Fetching policy record and issuance ledger..." rows={5} />
        </div>
      </>
    );
  }

  if (error) {
    return (
      <>
        <PageHeader title="Policy Details" subtitle="Request Error" />
        <div className="page-body">
          <ErrorState
            title={error.status === 404 ? 'Policy Request Not Found' : 'Unable to Load Policy'}
            message={error.message}
            status={error.status}
            onRetry={() => loadPolicy(false)}
          />
        </div>
      </>
    );
  }

  if (!policy) return null;

  const isIssued = policy.status === 'ISSUED';
  const isPending = policy.status === 'PENDING_CONFIRMATION' || policy.status === 'NEEDS_RECONCILIATION';
  const canSubmit = policy.status === 'RECEIVED' || policy.status === 'VALIDATED' || policy.status === 'FAILED';

  return (
    <>
      <PageHeader
        title={`Policy #${policy.id} — ${policy.client_request_id}`}
        subtitle={`Customer: ${policy.customer_name} • Partner: ${policy.agent_id}`}
        breadcrumbs={[
          { label: 'Policy Requests', to: '/policies' },
          { label: `#${policy.id}` },
        ]}
        actions={
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <Button
              variant="secondary"
              size="sm"
              icon={<ArrowLeft size={14} />}
              onClick={() => navigate('/policies')}
            >
              Back to List
            </Button>
            <Button
              variant="secondary"
              size="sm"
              icon={<RefreshCw size={14} className={refreshing ? 'spinner' : ''} />}
              onClick={() => loadPolicy(true)}
              disabled={refreshing}
            >
              {refreshing ? 'Refreshing...' : 'Refresh'}
            </Button>
          </div>
        }
      />

      <div className="page-body">
        {/* Action / Result Feedback Alert */}
        {actionFeedback && (
          <div
            style={{
              marginBottom: '1.5rem',
              padding: '1rem 1.25rem',
              borderRadius: 'var(--radius-md)',
              backgroundColor:
                actionFeedback.type === 'success'
                  ? 'var(--color-success-light)'
                  : actionFeedback.type === 'warning'
                  ? 'var(--color-warning-light)'
                  : 'var(--color-danger-light)',
              border: `1px solid ${
                actionFeedback.type === 'success'
                  ? 'var(--color-success-border)'
                  : actionFeedback.type === 'warning'
                  ? 'var(--color-warning-border)'
                  : 'var(--color-danger-border)'
              }`,
              color: 'var(--color-text-primary)',
              fontSize: '0.8125rem',
              display: 'flex',
              alignItems: 'center',
              gap: '0.75rem',
            }}
          >
            {actionFeedback.type === 'success' ? (
              <CheckCircle2 size={18} style={{ color: 'var(--color-success)' }} />
            ) : (
              <AlertTriangle size={18} style={{ color: 'var(--color-warning)' }} />
            )}
            <span>{actionFeedback.message}</span>
          </div>
        )}

        {/* Primary Action Card: Issuance Submission / Status Callout */}
        <div
          className="card"
          style={{
            marginBottom: '1.5rem',
            borderLeft: `4px solid ${
              isIssued ? 'var(--color-success)' : isPending ? 'var(--color-warning)' : 'var(--color-primary)'
            }`,
          }}
        >
          <div className="card-body" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '1rem' }}>
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                <StatusBadge status={policy.status} />
                {policy.policy_number && (
                  <span style={{ fontSize: '0.875rem', fontWeight: 600, color: 'var(--color-success-text)' }}>
                    Policy Number: <code>{policy.policy_number}</code>
                  </span>
                )}
                {policy.provider_reference && (
                  <span style={{ fontSize: '0.75rem', color: 'var(--color-text-secondary)' }}>
                    Ref: <code>{policy.provider_reference}</code>
                  </span>
                )}
              </div>
              <div style={{ fontSize: '0.8125rem', color: 'var(--color-text-secondary)', marginTop: '0.35rem' }}>
                {isIssued
                  ? 'Policy contract has been finalized and issued by the insurer. Record is locked and immutable.'
                  : isPending
                  ? 'Issuance confirmation is pending from insurer. Query status via reconciliation before re-submitting.'
                  : 'Policy request is ready for insurer submission and policy number generation.'}
              </div>
            </div>

            {/* Action Buttons */}
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              {canSubmit && (
                <>
                  {/* Test Scenario Selector */}
                  <select
                    className="form-control"
                    style={{ width: 'auto', height: '36px', fontSize: '0.8125rem' }}
                    value={selectedScenario}
                    onChange={(e) => setSelectedScenario(e.target.value)}
                    title="Mock Insurer Scenario Injection"
                  >
                    <option value="SUCCESS">Scenario: SUCCESS</option>
                    <option value="TEMPORARY_FAILURE">Scenario: TEMPORARY_FAILURE (503)</option>
                    <option value="TIMEOUT_AFTER_ISSUE">Scenario: TIMEOUT_AFTER_ISSUE (504)</option>
                    <option value="DELAYED_CALLBACK">Scenario: DELAYED_CALLBACK (202)</option>
                  </select>

                  <Button
                    variant="primary"
                    loading={submitting}
                    icon={<Send size={14} />}
                    onClick={handleSubmitIssuance}
                  >
                    {submitting ? 'Submitting...' : 'Submit to Insurer'}
                  </Button>
                </>
              )}

              {isPending && (
                <Button
                  variant="secondary"
                  icon={<RefreshCw size={14} />}
                  onClick={() => navigate('/operations')}
                >
                  Open Operations & Reconcile
                </Button>
              )}
            </div>
          </div>
        </div>

        {/* State Machine Lifecycle Tracker */}
        <PolicyLifecycleTracker status={policy.status} />

        {/* Four Grouped Info Sections */}
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
            gap: '1.5rem',
            marginBottom: '1.5rem',
          }}
        >
          {/* 1. Customer Information */}
          <div className="card">
            <div className="card-header">
              <h4 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', fontSize: '0.875rem' }}>
                <User size={15} style={{ color: 'var(--color-primary)' }} />
                <span>Customer Profile</span>
              </h4>
            </div>
            <div className="card-body" style={{ fontSize: '0.8125rem', display: 'flex', flexDirection: 'column', gap: '0.6rem' }}>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Full Name:</span>{' '}
                <strong>{policy.customer_name}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Date of Birth:</span>{' '}
                <span>{policy.date_of_birth}</span>
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Age at Trip Start:</span>{' '}
                <strong>{policy.age_multiplier ? `${policy.travel_days ? '' : ''}` : ''}{policy.date_of_birth && policy.trip_start ? `${new Date(policy.trip_start).getFullYear() - new Date(policy.date_of_birth).getFullYear()} yrs` : '—'}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Partner Agent ID:</span>{' '}
                <code>{policy.agent_id}</code>
              </div>
            </div>
          </div>

          {/* 2. Travel Itinerary */}
          <div className="card">
            <div className="card-header">
              <h4 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', fontSize: '0.875rem' }}>
                <Plane size={15} style={{ color: 'var(--color-primary)' }} />
                <span>Travel Schedule</span>
              </h4>
            </div>
            <div className="card-body" style={{ fontSize: '0.8125rem', display: 'flex', flexDirection: 'column', gap: '0.6rem' }}>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Destination:</span>{' '}
                <strong>{policy.destination}</strong> ({policy.destination_multiplier}x multiplier)
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Trip Start:</span>{' '}
                <span>{policy.trip_start}</span>
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Trip End:</span>{' '}
                <span>{policy.trip_end}</span>
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Inclusive Duration:</span>{' '}
                <strong>{policy.travel_days} {policy.travel_days === 1 ? 'day' : 'days'}</strong>
              </div>
            </div>
          </div>

          {/* 3. Coverage & Financials */}
          <div className="card">
            <div className="card-header">
              <h4 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', fontSize: '0.875rem' }}>
                <FileCheck size={15} style={{ color: 'var(--color-primary)' }} />
                <span>Coverage & Pricing</span>
              </h4>
            </div>
            <div className="card-body" style={{ fontSize: '0.8125rem', display: 'flex', flexDirection: 'column', gap: '0.6rem' }}>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Plan Level:</span>{' '}
                <strong>{policy.plan_code}</strong> (₹{policy.base_daily_rate} / day)
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Sum Insured:</span>{' '}
                <strong>₹{policy.sum_insured}</strong> ({policy.coverage_multiplier}x)
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Age Multiplier:</span>{' '}
                <span>{policy.age_multiplier}x</span>
              </div>
              <div style={{ paddingTop: '0.4rem', borderTop: '1px solid var(--color-border)', fontSize: '0.9375rem', color: 'var(--color-primary)' }}>
                <span>Final Premium:</span>{' '}
                <strong>₹{policy.final_premium}</strong>
              </div>
            </div>
          </div>

          {/* 4. Insurer Contract Identity */}
          <div className="card">
            <div className="card-header">
              <h4 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', fontSize: '0.875rem' }}>
                <ShieldCheck size={15} style={{ color: 'var(--color-primary)' }} />
                <span>Contract & Audit</span>
              </h4>
            </div>
            <div className="card-body" style={{ fontSize: '0.8125rem', display: 'flex', flexDirection: 'column', gap: '0.6rem' }}>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Policy Number:</span>{' '}
                {policy.policy_number ? (
                  <strong style={{ color: 'var(--color-success-text)' }}>{policy.policy_number}</strong>
                ) : (
                  <span style={{ color: 'var(--color-text-muted)' }}>Not issued yet</span>
                )}
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Provider Reference:</span>{' '}
                {policy.provider_reference ? (
                  <code>{policy.provider_reference}</code>
                ) : (
                  <span style={{ color: 'var(--color-text-muted)' }}>None</span>
                )}
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Created:</span>{' '}
                <span>{new Date(policy.created_at).toLocaleString()}</span>
              </div>
              <div>
                <span style={{ color: 'var(--color-text-secondary)' }}>Updated:</span>{' '}
                <span>{new Date(policy.updated_at).toLocaleString()}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Issuance Attempts Ledger */}
        <IssuanceAttemptTimeline attempts={policy.attempts} />

        {/* State Transition History Ledger */}
        <StateHistoryTimeline history={policy.state_history} />
      </div>
    </>
  );
};
