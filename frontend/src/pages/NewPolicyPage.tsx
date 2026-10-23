import React, { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Send,
  AlertCircle,
  CheckCircle2,
  Sparkles,
  ArrowRight,
  ShieldAlert,
} from 'lucide-react';
import { PageHeader } from '../components/layout/PageHeader';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Select } from '../components/ui/Select';
import { PremiumBreakdown } from '../components/policy/PremiumBreakdown';
import { calculateEstimatedPremium, type PricingEstimate } from '../services/pricing/calculator';
import { createPolicyRequest } from '../services/api/policies';
import type { Destination, PlanCode, PolicyRequest } from '../types';

export const NewPolicyPage: React.FC = () => {
  const navigate = useNavigate();

  // Form State
  const [clientRequestId, setClientRequestId] = useState(() => `AGT-REQ-${Date.now().toString().slice(-6)}`);
  const [agentId, setAgentId] = useState('AGENT-01');
  const [customerName, setCustomerName] = useState('');
  const [dateOfBirth, setDateOfBirth] = useState('');
  const [destination, setDestination] = useState<Destination>('INTERNATIONAL');
  const [tripStart, setTripStart] = useState('');
  const [tripEnd, setTripEnd] = useState('');
  const [planCode, setPlanCode] = useState<PlanCode>('PLUS');
  const [sumInsured, setSumInsured] = useState<number>(300000);

  // Status & Validation State
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);
  const [createdPolicy, setCreatedPolicy] = useState<PolicyRequest | null>(null);
  const [submissionMeta, setSubmissionMeta] = useState<{ idempotent_replay?: boolean; payload_mismatch?: boolean } | null>(null);
  const [submissionError, setSubmissionError] = useState<{ code: string; message: string; isTimeout?: boolean } | null>(null);

  // Dynamic Live Pricing Estimate
  const estimate: PricingEstimate | null = useMemo(() => {
    try {
      if (!dateOfBirth || !tripStart || !tripEnd) {
        return null;
      }
      return calculateEstimatedPremium({
        planCode,
        sumInsured,
        destination,
        dateOfBirth,
        tripStart,
        tripEnd,
      });
    } catch {
      return null;
    }
  }, [planCode, sumInsured, destination, dateOfBirth, tripStart, tripEnd]);

  // Load the authoritative assignment reference scenario (₹225.00)
  const loadReferenceCase = () => {
    setClientRequestId(`AGT-REF-${Date.now().toString().slice(-4)}`);
    setAgentId('AGENT-17');
    setCustomerName('Siddharth Verma');
    setDateOfBirth('1998-04-10');
    setDestination('INTERNATIONAL');
    setTripStart('2026-11-10');
    setTripEnd('2026-11-14');
    setPlanCode('PLUS');
    setSumInsured(300000);
    setFieldErrors({});
    setSubmissionError(null);
  };

  const validate = (): boolean => {
    const errors: Record<string, string> = {};

    if (!clientRequestId.trim()) errors.client_request_id = 'Client Request ID is required.';
    if (!agentId.trim()) errors.agent_id = 'Agent ID is required.';
    if (!customerName.trim()) errors.customer_name = 'Customer name is required.';
    if (!dateOfBirth) errors.date_of_birth = 'Date of birth is required.';
    if (!tripStart) errors.trip_start = 'Trip start date is required.';
    if (!tripEnd) errors.trip_end = 'Trip end date is required.';

    if (tripStart && tripEnd && tripEnd < tripStart) {
      errors.trip_end = 'Trip end date cannot be earlier than trip start date.';
    }

    if (dateOfBirth && tripStart && dateOfBirth > tripStart) {
      errors.date_of_birth = 'Date of birth cannot be after trip start date.';
    }

    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    setSubmitting(true);
    setSubmissionError(null);

    try {
      const result = await createPolicyRequest({
        client_request_id: clientRequestId.trim(),
        agent_id: agentId.trim(),
        customer_name: customerName.trim(),
        date_of_birth: dateOfBirth,
        destination,
        trip_start: tripStart,
        trip_end: tripEnd,
        plan_code: planCode,
        sum_insured: Number(sumInsured),
      });

      setCreatedPolicy(result.data);
      setSubmissionMeta(result.meta || null);
    } catch (err: unknown) {
      const errorObj = err as { code?: string; message?: string; status?: number; fields?: Record<string, string[]> };
      const isTimeout = errorObj.status === 504 || errorObj.code === 'TIMEOUT_ERROR';

      if (errorObj.fields) {
        const mappedErrors: Record<string, string> = {};
        for (const [key, msgs] of Object.entries(errorObj.fields)) {
          mappedErrors[key] = msgs[0] || 'Invalid value.';
        }
        setFieldErrors(mappedErrors);
      }

      setSubmissionError({
        code: errorObj.code || 'SUBMISSION_FAILED',
        message: errorObj.message || 'An error occurred while creating the policy request.',
        isTimeout,
      });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <PageHeader
        title="New Policy Intake"
        subtitle="Submit a customer travel insurance purchase request with dynamic pricing snapshot"
        actions={
          <Button
            variant="secondary"
            size="sm"
            icon={<Sparkles size={14} />}
            onClick={loadReferenceCase}
          >
            Load Reference Case (₹225.00)
          </Button>
        }
      />

      <div className="page-body">
        {/* Success / Idempotent Confirmation Banner */}
        {createdPolicy && (
          <div
            style={{
              marginBottom: '2rem',
              padding: '1.25rem 1.5rem',
              borderRadius: 'var(--radius-lg)',
              backgroundColor: 'var(--color-success-light)',
              border: '1px solid var(--color-success-border)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              boxShadow: 'var(--shadow-sm)',
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.875rem' }}>
              <CheckCircle2 size={24} style={{ color: 'var(--color-success)' }} />
              <div>
                <h4 style={{ fontSize: '0.9375rem', fontWeight: 600, color: 'var(--color-success-text)' }}>
                  {submissionMeta?.idempotent_replay
                    ? 'Idempotent Replay: Existing Policy Request Retrieved'
                    : 'Policy Request Successfully Created & Persisted'}
                </h4>
                <p style={{ fontSize: '0.8125rem', color: 'var(--color-text-secondary)', marginTop: '0.15rem' }}>
                  Request ID: <strong>{createdPolicy.client_request_id}</strong> • Confirmed Premium:{' '}
                  <strong>₹{createdPolicy.final_premium}</strong> • Status: <strong>{createdPolicy.status}</strong>
                </p>
                {submissionMeta?.payload_mismatch && (
                  <p style={{ fontSize: '0.75rem', color: 'var(--color-warning-text)', marginTop: '0.25rem' }}>
                    Warning: Idempotency key was reused with altered parameters. Original record preserved.
                  </p>
                )}
              </div>
            </div>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <Button
                variant="primary"
                size="sm"
                onClick={() => navigate(`/policies/${createdPolicy.id}`)}
              >
                View Details & Submit <ArrowRight size={13} />
              </Button>
            </div>
          </div>
        )}

        {/* Ambiguous Timeout Error Banner */}
        {submissionError?.isTimeout && (
          <div
            style={{
              marginBottom: '2rem',
              padding: '1.25rem 1.5rem',
              borderRadius: 'var(--radius-lg)',
              backgroundColor: 'var(--color-warning-light)',
              border: '1px solid var(--color-warning-border)',
              display: 'flex',
              alignItems: 'flex-start',
              gap: '0.875rem',
            }}
          >
            <ShieldAlert size={24} style={{ color: 'var(--color-warning)', flexShrink: 0 }} />
            <div>
              <h4 style={{ fontSize: '0.9375rem', fontWeight: 600, color: 'var(--color-warning-text)' }}>
                Network Timeout Encountered (Outcome Unknown)
              </h4>
              <p style={{ fontSize: '0.8125rem', color: 'var(--color-text-primary)', marginTop: '0.2rem' }}>
                The request timed out while awaiting the server response. To protect against duplicate policies,
                the Client Request ID (<code>{clientRequestId}</code>) has been retained. You can safely retry submission
                using the same ID or check the Policy Requests list to confirm if it was recorded.
              </p>
            </div>
          </div>
        )}

        {/* General Error Banner */}
        {submissionError && !submissionError.isTimeout && (
          <div
            style={{
              marginBottom: '2rem',
              padding: '1rem 1.25rem',
              borderRadius: 'var(--radius-md)',
              backgroundColor: 'var(--color-danger-light)',
              border: '1px solid var(--color-danger-border)',
              display: 'flex',
              alignItems: 'center',
              gap: '0.75rem',
            }}
          >
            <AlertCircle size={20} style={{ color: 'var(--color-danger)' }} />
            <div style={{ fontSize: '0.8125rem', color: 'var(--color-danger-text)' }}>
              <strong>Submission Error ({submissionError.code}):</strong> {submissionError.message}
            </div>
          </div>
        )}

        {/* Two-Column Form & Summary Layout */}
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'minmax(0, 1.4fr) minmax(320px, 1fr)',
            gap: '2rem',
            alignItems: 'start',
          }}
          className="form-grid-2"
        >
          {/* Left Column: Intake Form */}
          <form onSubmit={handleSubmit}>
            <div className="card">
              <div className="card-header">
                <div>
                  <h3 className="card-title">Policy Intake Details</h3>
                  <p className="card-description">
                    Input traveler information and coverage plan parameters
                  </p>
                </div>
              </div>

              <div className="card-body">
                {/* Section A: Request & Agent Identifiers */}
                <h4 style={{ fontSize: '0.8125rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--color-text-muted)', letterSpacing: '0.05em', marginBottom: '1rem' }}>
                  A. Request & Agent Identity
                </h4>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
                  <Input
                    label="Client Request ID (Idempotency Key)"
                    required
                    value={clientRequestId}
                    onChange={(e) => setClientRequestId(e.target.value)}
                    error={fieldErrors.client_request_id}
                    hint="Unique partner transaction identifier"
                  />
                  <Input
                    label="Partner Agent ID"
                    required
                    value={agentId}
                    onChange={(e) => setAgentId(e.target.value)}
                    error={fieldErrors.agent_id}
                  />
                </div>

                {/* Section B: Customer Information */}
                <h4 style={{ fontSize: '0.8125rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--color-text-muted)', letterSpacing: '0.05em', margin: '1.5rem 0 1rem' }}>
                  B. Customer Information
                </h4>
                <div style={{ display: 'grid', gridTemplateColumns: '1.2fr 0.8fr', gap: '1rem' }}>
                  <Input
                    label="Customer Full Name"
                    required
                    placeholder="e.g. Siddharth Verma"
                    value={customerName}
                    onChange={(e) => setCustomerName(e.target.value)}
                    error={fieldErrors.customer_name}
                  />
                  <Input
                    label="Date of Birth"
                    type="date"
                    required
                    value={dateOfBirth}
                    onChange={(e) => setDateOfBirth(e.target.value)}
                    error={fieldErrors.date_of_birth}
                    hint="Age determines multiplier"
                  />
                </div>

                {/* Section C: Travel Details */}
                <h4 style={{ fontSize: '0.8125rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--color-text-muted)', letterSpacing: '0.05em', margin: '1.5rem 0 1rem' }}>
                  C. Travel Schedule
                </h4>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '1rem' }}>
                  <Select
                    label="Destination"
                    required
                    value={destination}
                    onChange={(e) => setDestination(e.target.value as Destination)}
                    options={[
                      { value: 'DOMESTIC', label: 'Domestic (1.0x)' },
                      { value: 'INTERNATIONAL', label: 'International (1.8x)' },
                    ]}
                  />
                  <Input
                    label="Trip Start Date"
                    type="date"
                    required
                    value={tripStart}
                    onChange={(e) => setTripStart(e.target.value)}
                    error={fieldErrors.trip_start}
                  />
                  <Input
                    label="Trip End Date"
                    type="date"
                    required
                    value={tripEnd}
                    onChange={(e) => setTripEnd(e.target.value)}
                    error={fieldErrors.trip_end}
                    hint="Dates are inclusive"
                  />
                </div>

                {/* Section D: Coverage Selection */}
                <h4 style={{ fontSize: '0.8125rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--color-text-muted)', letterSpacing: '0.05em', margin: '1.5rem 0 1rem' }}>
                  D. Plan & Sum Insured
                </h4>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
                  <Select
                    label="Plan Level"
                    required
                    value={planCode}
                    onChange={(e) => setPlanCode(e.target.value as PlanCode)}
                    options={[
                      { value: 'BASIC', label: 'BASIC (₹12 / day)' },
                      { value: 'PLUS', label: 'PLUS (₹20 / day)' },
                      { value: 'PREMIUM', label: 'PREMIUM (₹30 / day)' },
                    ]}
                  />
                  <Select
                    label="Sum Insured (Coverage Amount)"
                    required
                    value={sumInsured}
                    onChange={(e) => setSumInsured(Number(e.target.value))}
                    options={[
                      { value: 100000, label: '₹100,000 (1.00x multiplier)' },
                      { value: 300000, label: '₹300,000 (1.25x multiplier)' },
                      { value: 500000, label: '₹500,000 (1.50x multiplier)' },
                    ]}
                  />
                </div>
              </div>

              <div className="card-footer" style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem' }}>
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => navigate('/policies')}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  variant="primary"
                  loading={submitting}
                  icon={<Send size={15} />}
                >
                  {submitting ? 'Submitting Intake...' : 'Create Policy Request'}
                </Button>
              </div>
            </div>
          </form>

          {/* Right Column: Live Dynamic Pricing Breakdown Panel */}
          <div>
            <PremiumBreakdown estimate={estimate} isAuthoritative={false} />

            <div
              className="card"
              style={{
                marginTop: '1.5rem',
                backgroundColor: 'var(--color-surface)',
                border: '1px solid var(--color-border)',
              }}
            >
              <div className="card-body" style={{ fontSize: '0.75rem', color: 'var(--color-text-secondary)', lineHeight: 1.6 }}>
                <strong>Idempotency Assurance:</strong> The composite key <code>(agent_id, client_request_id)</code> is
                atomically enforced by PostgreSQL / MySQL. Submitting identical credentials repeatedly returns the canonical
                record with zero duplicate calculations.
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
};
