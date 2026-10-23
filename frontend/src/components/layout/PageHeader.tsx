import React from 'react';
import { Menu } from 'lucide-react';
import { useOutletContext } from 'react-router-dom';

interface PageHeaderProps {
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
  breadcrumbs?: Array<{ label: string; to?: string }>;
}

export const PageHeader: React.FC<PageHeaderProps> = ({
  title,
  subtitle,
  actions,
  breadcrumbs,
}) => {
  const context = useOutletContext<{ toggleSidebar?: () => void } | null>();

  return (
    <header className="page-header">
      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
        {context?.toggleSidebar && (
          <button
            className="btn btn-ghost btn-sm"
            onClick={context.toggleSidebar}
            aria-label="Toggle navigation menu"
            style={{ padding: '0.35rem', borderRadius: 'var(--radius-md)' }}
          >
            <Menu size={20} />
          </button>
        )}
        <div>
          {breadcrumbs && breadcrumbs.length > 0 && (
            <nav aria-label="Breadcrumb" style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)', marginBottom: '0.1rem' }}>
              {breadcrumbs.map((bc, idx) => (
                <span key={idx}>
                  {idx > 0 && <span style={{ margin: '0 0.35rem' }}>/</span>}
                  {bc.to ? (
                    <a href={bc.to} style={{ color: 'var(--color-text-secondary)' }}>
                      {bc.label}
                    </a>
                  ) : (
                    <span>{bc.label}</span>
                  )}
                </span>
              ))}
            </nav>
          )}
          <h1 style={{ fontSize: '1.25rem', fontWeight: 700, letterSpacing: '-0.02em', color: 'var(--color-text-primary)' }}>
            {title}
          </h1>
          {subtitle && (
            <p style={{ fontSize: '0.8125rem', color: 'var(--color-text-secondary)', marginTop: '0.1rem' }}>
              {subtitle}
            </p>
          )}
        </div>
      </div>

      {actions && (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          {actions}
        </div>
      )}
    </header>
  );
};
