import React from 'react';
import { useNavigate } from 'react-router-dom';
import { FileQuestion } from 'lucide-react';
import { PageHeader } from '../components/layout/PageHeader';
import { EmptyState } from '../components/ui/EmptyState';

export const NotFoundPage: React.FC = () => {
  const navigate = useNavigate();

  return (
    <>
      <PageHeader title="404 — Page Not Found" subtitle="Requested resource does not exist" />
      <div className="page-body">
        <div className="card">
          <EmptyState
            title="Page Not Found"
            description="The URL you navigated to does not match any operational route in PolicyFlow."
            icon={<FileQuestion size={48} style={{ color: 'var(--color-text-muted)' }} />}
            actionText="Return to Overview"
            onAction={() => navigate('/')}
          />
        </div>
      </div>
    </>
  );
};
