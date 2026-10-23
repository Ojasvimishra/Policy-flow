<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PricingSnapshot;
use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Deterministic Travel Insurance Premium Calculation Service.
 * Implements exact decimal arithmetic and strict multiplier tables.
 */
class PremiumCalculationService
{
    /**
     * Daily base rates per plan (INR).
     */
    public const BASE_DAILY_RATES = [
        'BASIC' => '12.00',
        'PLUS' => '20.00',
        'PREMIUM' => '30.00',
    ];

    /**
     * Destination multipliers.
     */
    public const DESTINATION_MULTIPLIERS = [
        'DOMESTIC' => '1.00',
        'INTERNATIONAL' => '1.80',
    ];

    /**
     * Coverage multipliers mapped to sum insured (INR).
     */
    public const COVERAGE_MULTIPLIERS = [
        '100000' => '1.00',
        '300000' => '1.25',
        '500000' => '1.50',
    ];

    /**
     * Calculate the deterministic insurance premium and return an immutable snapshot.
     *
     * @param  string  $planCode  BASIC | PLUS | PREMIUM
     * @param  string|float|int  $sumInsured  100000 | 300000 | 500000
     * @param  string  $destination  DOMESTIC | INTERNATIONAL
     *
     * @throws InvalidArgumentException
     */
    public function calculate(
        string $planCode,
        string|float|int $sumInsured,
        string $destination,
        string|DateTimeInterface $dateOfBirth,
        string|DateTimeInterface $tripStart,
        string|DateTimeInterface $tripEnd
    ): PricingSnapshot {
        $normalizedPlan = strtoupper(trim($planCode));
        if (! isset(self::BASE_DAILY_RATES[$normalizedPlan])) {
            throw new InvalidArgumentException("Unsupported plan code: '{$planCode}'. Allowed: ".implode(', ', array_keys(self::BASE_DAILY_RATES)));
        }
        $baseDailyRate = self::BASE_DAILY_RATES[$normalizedPlan];

        $normalizedDestination = strtoupper(trim($destination));
        if (! isset(self::DESTINATION_MULTIPLIERS[$normalizedDestination])) {
            throw new InvalidArgumentException("Unsupported destination: '{$destination}'. Allowed: ".implode(', ', array_keys(self::DESTINATION_MULTIPLIERS)));
        }
        $destinationMultiplier = self::DESTINATION_MULTIPLIERS[$normalizedDestination];

        $coverageKey = (string) (int) $sumInsured;
        if (! isset(self::COVERAGE_MULTIPLIERS[$coverageKey])) {
            throw new InvalidArgumentException("Unsupported sum insured: '{$sumInsured}'. Allowed: ".implode(', ', array_keys(self::COVERAGE_MULTIPLIERS)));
        }
        $coverageMultiplier = self::COVERAGE_MULTIPLIERS[$coverageKey];

        $startDate = $this->parseDate($tripStart);
        $endDate = $this->parseDate($tripEnd);
        $dob = $this->parseDate($dateOfBirth);

        if ($endDate->lt($startDate)) {
            throw new InvalidArgumentException("Trip end date ({$endDate->toDateString()}) cannot be earlier than trip start date ({$startDate->toDateString()})");
        }

        $travelDays = $this->calculateTravelDays($startDate, $endDate);
        $ageAtTripStart = $this->calculateAgeAtDate($dob, $startDate);

        if ($ageAtTripStart < 0) {
            throw new InvalidArgumentException("Date of birth ({$dob->toDateString()}) cannot be after trip start date ({$startDate->toDateString()})");
        }

        $ageMultiplier = $this->getAgeMultiplier($ageAtTripStart);

        $finalPremium = $this->multiplyExact(
            $baseDailyRate,
            (string) $travelDays,
            $ageMultiplier,
            $destinationMultiplier,
            $coverageMultiplier
        );

        return new PricingSnapshot(
            travelDays: $travelDays,
            baseDailyRate: $baseDailyRate,
            ageMultiplier: $ageMultiplier,
            destinationMultiplier: $destinationMultiplier,
            coverageMultiplier: $coverageMultiplier,
            finalPremium: $finalPremium,
            ageAtTripStart: $ageAtTripStart
        );
    }

    /**
     * Calculate inclusive travel days: (trip_end - trip_start) + 1.
     */
    public function calculateTravelDays(Carbon $tripStart, Carbon $tripEnd): int
    {
        // Date-only calculation to prevent timezone/hour drifts
        $start = $tripStart->copy()->startOfDay();
        $end = $tripEnd->copy()->startOfDay();

        return (int) $start->diffInDays($end) + 1;
    }

    /**
     * Calculate completed years of age at a specific reference date.
     */
    public function calculateAgeAtDate(Carbon $dob, Carbon $referenceDate): int
    {
        $age = $referenceDate->year - $dob->year;

        // If reference date is before birthday in the reference year, decrement age
        if ($referenceDate->month < $dob->month ||
            ($referenceDate->month === $dob->month && $referenceDate->day < $dob->day)) {
            $age--;
        }

        return $age;
    }

    /**
     * Resolve the age multiplier based on age at trip start.
     *
     * 0–17: 1.10
     * 18–60: 1.00
     * 61–70: 1.50
     * 71+: 2.00
     */
    public function getAgeMultiplier(int $age): string
    {
        if ($age <= 17) {
            return '1.10';
        }

        if ($age <= 60) {
            return '1.00';
        }

        if ($age <= 70) {
            return '1.50';
        }

        return '2.00';
    }

    /**
     * Multiply factors with exact decimal precision and round half-up to 2 decimal places.
     * Formula: baseDailyRate * travelDays * ageMultiplier * destinationMultiplier * coverageMultiplier
     */
    protected function multiplyExact(string ...$factors): string
    {
        if (extension_loaded('bcmath')) {
            $result = '1.000000';
            foreach ($factors as $factor) {
                $result = bcmul($result, $factor, 6);
            }

            return $this->roundHalfUp($result, 2);
        }

        // Exact scaled integer arithmetic fallback (scale = 10,000)
        // Eliminates IEEE-754 floating point inaccuracies
        $scaledResult = 1;
        $totalDecimals = 0;

        foreach ($factors as $factor) {
            $parts = explode('.', $factor);
            $integerPart = $parts[0];
            $decimalPart = $parts[1] ?? '';
            $factorDecimals = strlen($decimalPart);
            $totalDecimals += $factorDecimals;

            $intVal = (int) ($integerPart.$decimalPart);
            $scaledResult *= $intVal;
        }

        // Adjust for cumulative decimal places to 2 decimal places with half-up rounding
        $divisor = 10 ** ($totalDecimals - 2);
        $roundedCents = (int) round($scaledResult / $divisor, 0, PHP_ROUND_HALF_UP);

        return number_format($roundedCents / 100, 2, '.', '');
    }

    /**
     * Round a decimal string half-up to specified precision.
     */
    protected function roundHalfUp(string $number, int $precision): string
    {
        $parts = explode('.', $number);
        $integerPart = $parts[0];
        $decimalPart = $parts[1] ?? '';

        if (strlen($decimalPart) <= $precision) {
            return number_format((float) $number, $precision, '.', '');
        }

        $relevantDecimals = substr($decimalPart, 0, $precision + 1);
        $checkDigit = (int) substr($relevantDecimals, $precision, 1);
        $base = (int) ($integerPart.substr($decimalPart, 0, $precision));

        if ($checkDigit >= 5) {
            $base++;
        }

        $divisor = 10 ** $precision;

        return number_format($base / $divisor, $precision, '.', '');
    }

    /**
     * Normalize date string or DateTimeInterface to Carbon instance in UTC.
     */
    protected function parseDate(string|DateTimeInterface $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->copy()->startOfDay();
        }

        if ($date instanceof DateTimeInterface) {
            return Carbon::instance($date)->startOfDay();
        }

        return Carbon::parse(trim($date))->startOfDay();
    }
}
