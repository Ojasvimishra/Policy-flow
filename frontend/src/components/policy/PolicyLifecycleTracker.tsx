import React from 'react';
import { CheckCircle2, Clock, AlertTriangle, XCircle, ArrowRight } from 'lucide-react';
import type { PolicyStatus } from '../../types';

interface PolicyLifecycleTrackerProps {
  status: PolicyStatus;
}

export const PolicyLifecycleTracker: React.FC<PolicyLifecycleTrackerProps> = ({ status }) => {
  const steps: Array<{ key: PolicyStatus; label: string }> = [
    { key: 'RECEIVED', label: '1. Received' },
    { key: 'VALIDATED', label: '2. Validated' },
    { key: 'SUBMITTED', label: '3. Submitted' },
    { key: 'ISSUED', label: '4. Issued' },
  ];

  const getStepState = (stepKey: PolicyStatus) => {
    if (status === 'ISSUED') {
      return 'completed';
    }

    if (status === 'FAILED') {
      if (stepKey === 'ISSUED') return 'failed';
      return 'completed';
    }

    if (status === 'PENDING_CONFIRMATION' || status === 'NEEDS_RECONCILIATION') {
      if (stepKey === 'SUBMITTED') return 'warning';
      if (stepKey === 'ISSUED') return 'pending';
      return 'completed';
    }

    const order: PolicyStatus[] = ['RECEIVED', 'VALIDATED', 'SUBMITTED', 'ISSUED'];
    const currentIndex = order.indexOf(status);
    const stepIndex = order.indexOf(stepKey);

    if (stepIndex < currentIndex) return 'completed';
    if (stepIndex === currentIndex) return 'active';
    return 'upcoming';
  };

  const getStatusExplanation = () => {
    switch (status) {
      case 'RECEIVED':
        return 'Intake payload recorded with immutable pricing snapshot. Awaiting pre-issuance validation.';
      case 'VALIDATED':
        return 'Policy parameters, dates, and pricing formulas verified. Ready for insurer dispatch.';
      case 'SUBMITTED':
        return 'Issuance attempt currently dispatched to insurer gateway.';
      case 'PENDING_CONFIRMATION':
        return 'Connection timed out or provider accepted intake as asynchronous. Policy is awaiting authoritative status confirmation from webhook or reconciliation.';
      case 'NEEDS_RECONCILIATION':
        return 'Ambiguous provider response. Run reconciliation to query the insurer status before considering reissue.';
      case 'FAILED':
        return 'Provider returned a non-terminal failure or rejection. A safe retry can be performed.';
      case 'ISSUED':
        return 'Policy has been authoritatively issued by the insurer. A canonical policy number has been assigned and the record is immutable.';
      default:
        return 'Unknown lifecycle state.';
    }
  };

  return (
    <div className="card" style={{ marginBottom: '1.5rem' }}>
      <div className="card-header">
        <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <span>Policy Lifecycle Progression</span>
        </h3>
        <span style={{ fontSize: '0.8125rem', color: 'var(--color-text-secondary)' }}>
          State Machine Engine
        </span>
      </div>
      <div className="card-body">
        {/* Step progression bar */}
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '0.5rem', flexWrap: 'wrap' }}>
          {steps.map((step, idx) => {
            const state = getStepState(step.key);

            let bg = 'var(--color-surface-subtle)';
            let color = 'var(--color-text-muted)';
            let icon = <Clock size={16} />;

            if (state === 'completed') {
              bg = 'var(--color-success-light)';
              color = 'var(--color-success-text)';
              icon = <CheckCircle2 size={16} />;
            } else if (state === 'active') {
              bg = 'var(--color-primary-light)';
              color = 'var(--color-primary)';
              icon = <Clock size={16} />;
            } else if (state === 'warning') {
              bg = 'var(--color-warning-light)';
              color = 'var(--color-warning-text)';
              icon = <AlertTriangle size={16} />;
            } else if (state === 'failed') {
              bg = 'var(--color-danger-light)';
              color = 'var(--color-danger-text)';
              icon = <XCircle size={16} />;
            }

            return (
              <React.Fragment key={step.key}>
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '0.5rem',
                    padding: '0.5rem 0.875rem',
                    borderRadius: 'var(--radius-md)',
                    backgroundColor: bg,
                    color: color,
                    fontWeight: 600,
                    fontSize: '0.8125rem',
                    border: `1px solid ${state === 'completed' ? 'var(--color-success-border)' : 'var(--color-border)'}`,
                  }}
                >
                  {icon}
                  <span>{step.label}</span>
                </div>
                {idx < steps.length - 1 && (
                  <ArrowRight size={16} style={{ color: 'var(--color-text-muted)' }} />
                )}
              </React.Fragment>
            );
          })}
        </div>

        {/* State explanation alert */}
        <div
          style={{
            marginTop: '1.25rem',
            padding: '0.875rem 1rem',
            borderRadius: 'var(--radius-md)',
            backgroundColor:
              status === 'ISSUED'
                ? 'var(--color-success-light)'
                : status === 'FAILED'
                ? 'var(--color-danger-light)'
                : status === 'PENDING_CONFIRMATION'
                ? 'var(--color-warning-light)'
                : 'var(--color-surface-subtle)',
            borderLeft: `4px solid ${
              status === 'ISSUED'
                ? 'var(--color-success)'
                : status === 'FAILED'
                ? 'var(--color-danger)'
                : status === 'PENDING_CONFIRMATION'
                ? 'var(--color-warning)'
                : 'var(--color-primary)'
            }`,
            fontSize: '0.8125rem',
            color: 'var(--color-text-primary)',
            lineHeight: 1.5,
          }}
        >
          <strong>Operational Context: </strong>
          {getStatusExplanation()}
        </div>
      </div>
    </div>
  );
};
