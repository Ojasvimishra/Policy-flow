import React from 'react';
import { Send, CheckCircle, AlertTriangle, XCircle, Clock } from 'lucide-react';
import type { IssuanceAttempt } from '../../types';

interface IssuanceAttemptTimelineProps {
  attempts: IssuanceAttempt[] | undefined;
}

export const IssuanceAttemptTimeline: React.FC<IssuanceAttemptTimelineProps> = ({ attempts }) => {
  if (!attempts || attempts.length === 0) {
    return (
      <div className="card">
        <div className="card-header">
          <h3 className="card-title">Issuance Attempts Ledger</h3>
        </div>
        <div className="card-body">
          <p style={{ fontSize: '0.8125rem', color: 'var(--color-text-secondary)', textAlign: 'center', padding: '1rem' }}>
            No issuance attempts have been dispatched yet for this policy request.
          </p>
        </div>
      </div>
    );
  }

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'SUCCESS':
        return <CheckCircle size={16} className="text-teal-600" />;
      case 'TEMPORARY_FAILURE':
        return <AlertTriangle size={16} className="text-amber-600" />;
      case 'TIMEOUT':
        return <Clock size={16} className="text-amber-600" />;
      case 'FAILED':
        return <XCircle size={16} className="text-rose-600" />;
      default:
        return <Send size={16} className="text-blue-600" />;
    }
  };

  return (
    <div className="card">
      <div className="card-header">
        <div>
          <h3 className="card-title">Issuance Attempts Ledger</h3>
          <p className="card-description">Sequential log of external insurer interactions</p>
        </div>
        <span style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--color-text-secondary)' }}>
          {attempts.length} {attempts.length === 1 ? 'Attempt' : 'Attempts'}
        </span>
      </div>

      <div className="card-body">
        <div className="timeline">
          {attempts.map((attempt) => {
            const isSuccess = attempt.status === 'SUCCESS';
            const isFailure = attempt.status === 'FAILED' || attempt.status === 'TEMPORARY_FAILURE';
            const isWarning = attempt.status === 'TIMEOUT' || attempt.status === 'PENDING';

            let dotClass = '';
            if (isSuccess) dotClass = 'success';
            else if (isFailure) dotClass = 'danger';
            else if (isWarning) dotClass = 'warning';

            return (
              <div key={attempt.id || attempt.attempt_number} className="timeline-item">
                <div className={`timeline-dot ${dotClass}`} />
                <div className="timeline-header">
                  <span style={{ display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                    {getStatusIcon(attempt.status)}
                    <span>Attempt #{attempt.attempt_number}: {attempt.status}</span>
                    {attempt.response_status_code && (
                      <span
                        style={{
                          fontSize: '0.7rem',
                          padding: '0.1rem 0.35rem',
                          borderRadius: 'var(--radius-sm)',
                          backgroundColor: 'var(--color-surface-subtle)',
                          color: 'var(--color-text-secondary)',
                          marginLeft: '0.25rem',
                        }}
                      >
                        HTTP {attempt.response_status_code}
                      </span>
                    )}
                  </span>
                  <span className="timeline-time">
                    {new Date(attempt.attempted_at).toLocaleString()}
                  </span>
                </div>

                <div className="timeline-body">
                  <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', marginTop: '0.25rem' }}>
                    <span>Provider: <strong>{attempt.provider}</strong></span>
                    {attempt.scenario_requested && (
                      <span>Scenario: <code>{attempt.scenario_requested}</code></span>
                    )}
                  </div>

                  {attempt.error_detail && (
                    <div
                      style={{
                        marginTop: '0.5rem',
                        padding: '0.5rem 0.75rem',
                        borderRadius: 'var(--radius-sm)',
                        backgroundColor: 'var(--color-danger-light)',
                        color: 'var(--color-danger-text)',
                        fontSize: '0.75rem',
                      }}
                    >
                      {attempt.error_detail}
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};
