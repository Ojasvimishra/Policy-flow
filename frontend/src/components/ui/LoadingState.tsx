import React from 'react';

interface LoadingStateProps {
  message?: string;
  rows?: number;
}

export const LoadingState: React.FC<LoadingStateProps> = ({ message = 'Loading data...', rows = 3 }) => {
  return (
    <div className="state-container" role="status" aria-live="polite">
      <div style={{ width: '100%', maxWidth: '600px', display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
        {Array.from({ length: rows }).map((_, i) => (
          <div
            key={i}
            className="skeleton"
            style={{
              height: i === 0 ? '48px' : '36px',
              width: i === rows - 1 ? '70%' : '100%',
            }}
          />
        ))}
      </div>
      <p style={{ marginTop: '1.25rem', fontSize: '0.875rem', color: 'var(--color-text-secondary)' }}>
        {message}
      </p>
    </div>
  );
};
