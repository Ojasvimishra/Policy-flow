import React from 'react';
import type { PolicyStatus } from '../../types';

interface StatusBadgeProps {
  status: PolicyStatus | string;
  size?: 'sm' | 'md';
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({ status, size = 'md' }) => {
  const normalized = status ? status.toUpperCase().replace(/\s+/g, '_') : 'UNKNOWN';

  const formatLabel = (val: string) => {
    return val.replace(/_/g, ' ');
  };

  const styleClass = `badge badge-${normalized} ${size === 'sm' ? 'text-xs py-0.5' : ''}`;

  return (
    <span className={styleClass} title={`Policy Status: ${formatLabel(normalized)}`}>
      <span className="badge-dot" />
      <span>{formatLabel(normalized)}</span>
    </span>
  );
};
