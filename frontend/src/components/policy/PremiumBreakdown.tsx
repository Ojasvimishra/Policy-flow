import React from 'react';
import { Calculator, Info } from 'lucide-react';
import type { PricingEstimate } from '../../services/pricing/calculator';
import type { Destination, PlanCode } from '../../types';

interface PremiumBreakdownProps {
  estimate: PricingEstimate | null;
  isAuthoritative?: boolean;
  selectedPlan?: PlanCode;
  selectedDestination?: Destination;
  selectedSumInsured?: number;
}

export const PremiumBreakdown: React.FC<PremiumBreakdownProps> = ({
  estimate,
  isAuthoritative = false,
  selectedPlan = 'PLUS',
  selectedDestination = 'INTERNATIONAL',
  selectedSumInsured = 300000,
}) => {
  const baseRates: Record<PlanCode, number> = {
    BASIC: 12.0,
    PLUS: 20.0,
    PREMIUM: 30.0,
  };

  const destMultipliers: Record<Destination, number> = {
    DOMESTIC: 1.0,
    INTERNATIONAL: 1.8,
  };

  const covMultipliers: Record<number, number> = {
    100000: 1.0,
    300000: 1.25,
    500000: 1.5,
  };

  const previewBaseRate = baseRates[selectedPlan] ?? 20.0;
  const previewDestMultiplier = destMultipliers[selectedDestination] ?? 1.8;
  const previewCovMultiplier = covMultipliers[selectedSumInsured] ?? 1.25;

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
            color: isAuthoritative
              ? 'var(--color-success-text)'
              : estimate
              ? 'var(--color-primary)'
              : 'var(--color-text-muted)',
            background: isAuthoritative
              ? 'var(--color-success-light)'
              : estimate
              ? 'var(--color-primary-light)'
              : 'var(--color-surface-subtle)',
            padding: '0.2rem 0.5rem',
            borderRadius: 'var(--radius-sm)',
            border: `1px solid ${
              isAuthoritative
                ? 'var(--color-success-border)'
                : estimate
                ? 'var(--color-primary-border)'
                : 'var(--color-border)'
            }`,
          }}
        >
          {isAuthoritative
            ? 'Database Confirmed'
            : estimate
            ? 'Calculated Estimate'
            : 'Pending Dates & DOB'}
        </span>
      </div>

      <div className="card-body" style={{ padding: '1.25rem 1.5rem' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem', fontSize: '0.8125rem' }}>
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              paddingBottom: '0.5rem',
              borderBottom: '1px solid var(--color-border-subtle)',
            }}
          >
            <span style={{ color: 'var(--color-text-secondary)' }}>Base Daily Rate ({selectedPlan}):</span>
            <span style={{ fontWeight: 600, color: 'var(--color-text-primary)' }}>
              ₹{(estimate?.baseDailyRate ?? previewBaseRate).toFixed(2)} / day
            </span>
          </div>

          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              paddingBottom: '0.5rem',
              borderBottom: '1px solid var(--color-border-subtle)',
            }}
          >
            <span style={{ color: 'var(--color-text-secondary)' }}>Inclusive Duration:</span>
            {estimate ? (
              <span style={{ fontWeight: 600, color: 'var(--color-text-primary)' }}>
                {estimate.travelDays} {estimate.travelDays === 1 ? 'day' : 'days'}
              </span>
            ) : (
              <span style={{ fontStyle: 'italic', color: 'var(--color-text-muted)' }}>
                Select trip start & end dates
              </span>
            )}
          </div>

          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              paddingBottom: '0.5rem',
              borderBottom: '1px solid var(--color-border-subtle)',
            }}
          >
            <span style={{ color: 'var(--color-text-secondary)' }}>
              Age Multiplier {estimate ? `(Age ${estimate.age})` : ''}:
            </span>
            {estimate ? (
              <span style={{ fontWeight: 600, color: 'var(--color-text-primary)' }}>
                {estimate.ageMultiplier.toFixed(2)}x
              </span>
            ) : (
              <span style={{ fontStyle: 'italic', color: 'var(--color-text-muted)' }}>
                Enter date of birth
              </span>
            )}
          </div>

          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              paddingBottom: '0.5rem',
              borderBottom: '1px solid var(--color-border-subtle)',
            }}
          >
            <span style={{ color: 'var(--color-text-secondary)' }}>
              Destination ({selectedDestination}):
            </span>
            <span style={{ fontWeight: 600, color: 'var(--color-text-primary)' }}>
              {(estimate?.destinationMultiplier ?? previewDestMultiplier).toFixed(2)}x
            </span>
          </div>

          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              paddingBottom: '0.5rem',
              borderBottom: '1px solid var(--color-border-subtle)',
            }}
          >
            <span style={{ color: 'var(--color-text-secondary)' }}>
              Coverage Tier (₹{selectedSumInsured.toLocaleString('en-IN')}):
            </span>
            <span style={{ fontWeight: 600, color: 'var(--color-text-primary)' }}>
              {(estimate?.coverageMultiplier ?? previewCovMultiplier).toFixed(2)}x
            </span>
          </div>
        </div>

        {/* Total Box */}
        <div
          style={{
            marginTop: '1.25rem',
            padding: '1rem',
            backgroundColor: estimate
              ? 'var(--color-primary-light)'
              : 'var(--color-surface-subtle)',
            borderRadius: 'var(--radius-md)',
            border: `1px solid ${
              estimate ? 'var(--color-primary-border)' : 'var(--color-border)'
            }`,
            display: 'flex',
            alignItems: 'baseline',
            justifyContent: 'space-between',
          }}
        >
          <div>
            <div
              style={{
                fontSize: '0.75rem',
                fontWeight: 600,
                textTransform: 'uppercase',
                color: estimate ? 'var(--color-primary)' : 'var(--color-text-secondary)',
              }}
            >
              {isAuthoritative ? 'Final Premium' : 'Calculated Total'}
            </div>
            <div style={{ fontSize: '0.7rem', color: 'var(--color-text-secondary)', marginTop: '0.15rem' }}>
              Formula: Rate × Days × Multipliers
            </div>
          </div>
          <div
            style={{
              fontSize: '1.75rem',
              fontWeight: 700,
              color: estimate ? 'var(--color-primary)' : 'var(--color-text-muted)',
              fontVariantNumeric: 'tabular-nums',
            }}
          >
            {estimate ? `₹${estimate.estimatedTotal}` : '₹ —'}
          </div>
        </div>

        {!estimate && (
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '0.5rem',
              fontSize: '0.75rem',
              color: 'var(--color-text-secondary)',
              marginTop: '0.85rem',
              padding: '0.5rem 0.75rem',
              borderRadius: 'var(--radius-sm)',
              backgroundColor: 'var(--color-surface-subtle)',
            }}
          >
            <Info size={14} style={{ color: 'var(--color-primary)', flexShrink: 0 }} />
            <span>Fill in traveler date of birth and trip dates to compute exact premium.</span>
          </div>
        )}
      </div>
    </div>
  );
};
