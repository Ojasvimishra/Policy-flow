import React from 'react';
import { History, ArrowRight } from 'lucide-react';
import type { PolicyStateHistory } from '../../types';

interface StateHistoryTimelineProps {
  history: PolicyStateHistory[] | undefined;
}

export const StateHistoryTimeline: React.FC<StateHistoryTimelineProps> = ({ history }) => {
  if (!history || history.length === 0) {
    return null;
  }

  return (
    <div className="card" style={{ marginTop: '1.5rem' }}>
      <div className="card-header">
        <div>
          <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <History size={16} />
            <span>State Transition Audit Trail</span>
          </h3>
          <p className="card-description">Immutable ledger of lifecycle state changes</p>
        </div>
      </div>

      <div className="card-body">
        <div className="timeline">
          {history.map((record) => (
            <div key={record.id} className="timeline-item">
              <div className="timeline-dot" />
              <div className="timeline-header">
                <span style={{ display: 'flex', alignItems: 'center', gap: '0.35rem', fontSize: '0.8125rem' }}>
                  <code>{record.from_state}</code>
                  <ArrowRight size={12} style={{ color: 'var(--color-text-muted)' }} />
                  <strong><code>{record.to_state}</code></strong>
                </span>
                <span className="timeline-time">
                  {new Date(record.created_at).toLocaleString()}
                </span>
              </div>
              <div className="timeline-body">
                <div>Trigger: <strong>{record.trigger}</strong></div>
                {record.reason && (
                  <div style={{ color: 'var(--color-text-secondary)', fontSize: '0.75rem', marginTop: '0.15rem' }}>
                    {record.reason}
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
