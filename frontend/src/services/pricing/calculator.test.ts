import { describe, expect, it } from 'vitest';
import {
  calculateAgeAtDate,
  calculateEstimatedPremium,
  calculateInclusiveTravelDays,
  getAgeMultiplier,
} from './calculator';

describe('Pricing Calculator Engine', () => {
  it('calculates the authoritative reference case (₹225.00)', () => {
    // PLUS (20.00) * 5 days * 1.00 (age 28) * 1.80 (intl) * 1.25 (300k) = ₹225.00
    const result = calculateEstimatedPremium({
      planCode: 'PLUS',
      sumInsured: 300000,
      destination: 'INTERNATIONAL',
      dateOfBirth: '1998-04-10',
      tripStart: '2026-11-10',
      tripEnd: '2026-11-14',
    });

    expect(result.travelDays).toBe(5);
    expect(result.baseDailyRate).toBe(20.00);
    expect(result.ageMultiplier).toBe(1.00);
    expect(result.destinationMultiplier).toBe(1.80);
    expect(result.coverageMultiplier).toBe(1.25);
    expect(result.estimatedTotal).toBe('225.00');
  });

  it('verifies age boundaries strictly', () => {
    expect(getAgeMultiplier(17)).toBe(1.10);
    expect(getAgeMultiplier(18)).toBe(1.00);
    expect(getAgeMultiplier(60)).toBe(1.00);
    expect(getAgeMultiplier(61)).toBe(1.50);
    expect(getAgeMultiplier(70)).toBe(1.50);
    expect(getAgeMultiplier(71)).toBe(2.00);
  });

  it('calculates age at trip start date correctly across birthdays', () => {
    // Birthday is Nov 10, trip start is Nov 09 -> age is 27
    expect(calculateAgeAtDate('1998-11-10', '2026-11-09')).toBe(27);
    // Birthday is Nov 10, trip start is Nov 10 -> age is 28
    expect(calculateAgeAtDate('1998-11-10', '2026-11-10')).toBe(28);
  });

  it('calculates inclusive travel days correctly', () => {
    // Same-day trip (1 day)
    expect(calculateInclusiveTravelDays('2026-12-01', '2026-12-01')).toBe(1);
    // 5-day trip (Dec 01 to Dec 05 inclusive)
    expect(calculateInclusiveTravelDays('2026-12-01', '2026-12-05')).toBe(5);
  });

  it('rejects trip end earlier than trip start', () => {
    expect(() => calculateInclusiveTravelDays('2026-12-05', '2026-12-01')).toThrow(
      'Trip end date cannot be earlier than trip start date.'
    );
  });
});
