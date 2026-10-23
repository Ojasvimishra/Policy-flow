import React from 'react';
import { AlertCircle, RefreshCw } from 'lucide-react';
import { Button } from './Button';

interface ErrorStateProps {
  title?: string;
  message: string;
  onRetry?: () => void;
  status?: number;
}

export const ErrorState: React.FC<ErrorStateProps> = ({
  title = 'Failed to load data',
  message,
  onRetry,
  status,
}) => {
  return (
    <div className="state-container" role="alert">
      <div className="state-icon" style={{ color: 'var(--color-danger)' }}>
        <AlertCircle size={48} />
      </div>
      <h3 className="state-title">
        {title} {status ? `(${status})` : ''}
      </h3>
      <p className="state-description">{message}</p>
      {onRetry && (
        <Button variant="secondary" icon={<RefreshCw size={15} />} onClick={onRetry}>
          Try Again
        </Button>
      )}
    </div>
  );
};
