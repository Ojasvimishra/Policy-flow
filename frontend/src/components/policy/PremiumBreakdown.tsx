import React from 'react';
import { Calculator } from 'lucide-react';
import type { PricingEstimate } from '../../services/pricing/calculator';

interface PremiumBreakdownProps {
  estimate: PricingEstimate | null;
  isAuthoritative?: boolean;
}

export const PremiumBreakdown: React.FC<PremiumBreakdownProps> = ({
  estimate,
  isAuthoritative = false,
}) => {
  if (!estimate) {
    return (
      <div className="card" style={{ height: '100%', display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
        <div className="state-container" style={{ padding: '2rem 1.5rem' }}>
          <Calculator size={36} style={{ color: 'var(--color-text-muted)', marginBottom: '0.75rem' }} />
          <h4 style={{ fontSize: '0.9375rem', fontWeight: 600, color: 'var(--color-text-primary)' }}>
            Premium Estimation
          </h4>
          <p style={{ fontSize: '0.8125rem', color: 'var(--color-text-secondary)', marginTop: '0.25rem', textAlign: 'center' }}>
            Complete the form parameters to view the dynamic pricing breakdown and multiplier snapshot.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="card">
      <div className="card-header">
        <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <Calculator size={18} style={{ color: 'var(--color-primary)' }} />
          <span>{isAuthoritative ? 'Authoritative Pricing Snapshot' : 'Live Premium Estimate'}</span>
        </h3>
        <span
          style={{
            fontSize: '0.7rem',
            fontWeight: 600,
            textTransform: 'uppercase',
            color: isAuthoritative ? 'var(--color-success-text)' : 'var(--color-primary)',
            background: isAuthoritative ? 'var(--color-success-light)' : 'var(--color-primary-light)',
            padding: '0.2rem 0.5rem',
            borderRadius: 'var(--radius-sm)',
            border: `1px solid ${isAuthoritative ? 'var(--color-success-border)' : 'var(--color-primary-border)'}`,
          }}
        >
          {isAuthoritative ? 'Database Confirmed' : 'Client Calculation'}
        </span>
      </div>

      <div className="card-body" style={{ padding: '1.25rem 1.5rem' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem', fontSize: '0.8125rem' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', paddingBottom: '0.5rem', borderBottom: '1px solid var(--color-border-subtle)' }}>
            <span style={{ color: 'var(--color-text-secondary)' }}>Base Daily Rate:</span>
            <span style={{ fontWeight: 600, color: 'var(--color-text-primary)' }}>
              ₹{estimate.baseDailyRate.toFixed(2)} / day
            </span>
          </div>

          <div style={{ display: 'flex', justifyContent: 'space-between', paddingBottom: '0.5rem', borderBottom: '1px solid var(--color-border-subtle)' }}>
            <span style={{ color: 'var(--color-text-secondary)' }}>Inclusive Duration:</span>
            <span style={{ fontWeight: 600, color: 'var(--color-text-primary)' }}>
              {estimate.travelDays} {estimate.travelDays === 1 ? 'day' : 'days'}
            </span>
          </div>

          <div style={{ display: 'flex', justifyContent: 'space-between', paddingBottom: '0.5rem', borderBottom: '1px solid var(--color-border-subtle)' }}>
            <span style={{ color: 'var(--color-text-secondary)' }}>Age Multiplier (Age {estimate.age}):</span>
            <span style={{ fontWeight: 600, color: 'var(--color-text-primary)' }}>
              {estimate.ageMultiplier.toFixed(2)}x
            </span>
          </div>

          <div style={{ display: 'flex', justifyContent: 'space-between', paddingBottom: '0.5rem', borderBottom: '1px solid var(--color-border-subtle)' }}>
            <span style={{ color: 'var(--color-text-secondary)' }}>Destination Multiplier:</span>
            <span style={{ fontWeight: 600, color: 'var(--color-text-primary)' }}>
              {estimate.destinationMultiplier.toFixed(2)}x
            </span>
          </div>

          <div style={{ display: 'flex', justifyContent: 'space-between', paddingBottom: '0.5rem', borderBottom: '1px solid var(--color-border-subtle)' }}>
            <span style={{ color: 'var(--color-text-secondary)' }}>Coverage Multiplier:</span>
            <span style={{ fontWeight: 600, color: 'var(--color-text-primary)' }}>
              {estimate.coverageMultiplier.toFixed(2)}x
            </span>
          </div>
        </div>

        {/* Total Box */}
        <div
          style={{
            marginTop: '1.25rem',
            padding: '1rem',
            backgroundColor: 'var(--color-primary-light)',
            borderRadius: 'var(--radius-md)',
            border: '1px solid var(--color-primary-border)',
            display: 'flex',
            alignItems: 'baseline',
            justifyContent: 'space-between',
          }}
        >
          <div>
            <div style={{ fontSize: '0.75rem', fontWeight: 600, textTransform: 'uppercase', color: 'var(--color-primary)' }}>
              {isAuthoritative ? 'Final Premium' : 'Calculated Total'}
            </div>
            <div style={{ fontSize: '0.7rem', color: 'var(--color-text-secondary)' }}>
              Formula: Rate × Days × Multipliers
            </div>
          </div>
          <div style={{ fontSize: '1.75rem', fontWeight: 700, color: 'var(--color-primary)', fontVariantNumeric: 'tabular-nums' }}>
            ₹{estimate.estimatedTotal}
          </div>
        </div>
      </div>
    </div>
  );
};
