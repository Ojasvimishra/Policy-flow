import type { Destination, PlanCode } from '../../types';

export const BASE_DAILY_RATES: Record<PlanCode, number> = {
  BASIC: 12.00,
  PLUS: 20.00,
  PREMIUM: 30.00,
};

export const DESTINATION_MULTIPLIERS: Record<Destination, number> = {
  DOMESTIC: 1.00,
  INTERNATIONAL: 1.80,
};

export const COVERAGE_MULTIPLIERS: Record<number, number> = {
  100000: 1.00,
  300000: 1.25,
  500000: 1.50,
};

export function getAgeMultiplier(age: number): number {
  if (age <= 17) return 1.10;
  if (age <= 60) return 1.00;
  if (age <= 70) return 1.50;
  return 2.00;
}

export function calculateAgeAtDate(dobString: string, targetDateString: string): number {
  const [dobYear, dobMonth, dobDay] = dobString.split('-').map(Number);
  const [targetYear, targetMonth, targetDay] = targetDateString.split('-').map(Number);

  let age = targetYear - dobYear;
  if (targetMonth < dobMonth || (targetMonth === dobMonth && targetDay < dobDay)) {
    age--;
  }
  return age;
}

export function calculateInclusiveTravelDays(startDateString: string, endDateString: string): number {
  const start = new Date(startDateString);
  const end = new Date(endDateString);

  if (isNaN(start.getTime()) || isNaN(end.getTime())) {
    return 0;
  }

  // Calculate day difference irrespective of daylight saving time
  const utcStart = Date.UTC(start.getFullYear(), start.getMonth(), start.getDate());
  const utcEnd = Date.UTC(end.getFullYear(), end.getMonth(), end.getDate());

  if (utcEnd < utcStart) {
    throw new Error('Trip end date cannot be earlier than trip start date.');
  }

  const diffMs = utcEnd - utcStart;
  return Math.floor(diffMs / (1000 * 60 * 60 * 24)) + 1;
}

export interface PricingEstimate {
  travelDays: number;
  age: number;
  baseDailyRate: number;
  ageMultiplier: number;
  destinationMultiplier: number;
  coverageMultiplier: number;
  estimatedTotal: string;
}

export function calculateEstimatedPremium(params: {
  planCode: PlanCode;
  sumInsured: number;
  destination: Destination;
  dateOfBirth: string;
  tripStart: string;
  tripEnd: string;
}): PricingEstimate {
  const { planCode, sumInsured, destination, dateOfBirth, tripStart, tripEnd } = params;

  if (!planCode || !sumInsured || !destination || !dateOfBirth || !tripStart || !tripEnd) {
    throw new Error('All pricing input fields are required.');
  }

  const travelDays = calculateInclusiveTravelDays(tripStart, tripEnd);
  const age = calculateAgeAtDate(dateOfBirth, tripStart);

  if (age < 0) {
    throw new Error('Date of birth cannot be after trip start date.');
  }

  const baseDailyRate = BASE_DAILY_RATES[planCode] ?? 20.00;
  const ageMultiplier = getAgeMultiplier(age);
  const destinationMultiplier = DESTINATION_MULTIPLIERS[destination] ?? 1.00;
  const coverageMultiplier = COVERAGE_MULTIPLIERS[sumInsured] ?? 1.00;

  // Exact math: base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier
  // Use scaled integer arithmetic or round half up to prevent floating point inaccuracies
  const rawProduct = baseDailyRate * travelDays * ageMultiplier * destinationMultiplier * coverageMultiplier;
  const rounded = Math.round((rawProduct + Number.EPSILON) * 100) / 100;

  return {
    travelDays,
    age,
    baseDailyRate,
    ageMultiplier,
    destinationMultiplier,
    coverageMultiplier,
    estimatedTotal: rounded.toFixed(2),
  };
}
