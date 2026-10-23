<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Immutable Data Transfer Object representing a calculated insurance premium snapshot.
 */
class PricingSnapshot
{
    public function __construct(
        public readonly int $travelDays,
        public readonly string $baseDailyRate,
        public readonly string $ageMultiplier,
        public readonly string $destinationMultiplier,
        public readonly string $coverageMultiplier,
        public readonly string $finalPremium,
        public readonly int $ageAtTripStart
    ) {}

    /**
     * Convert to an array for model persistence and JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'travel_days' => $this->travelDays,
            'base_daily_rate' => $this->baseDailyRate,
            'age_multiplier' => $this->ageMultiplier,
            'destination_multiplier' => $this->destinationMultiplier,
            'coverage_multiplier' => $this->coverageMultiplier,
            'final_premium' => $this->finalPremium,
            'age_at_trip_start' => $this->ageAtTripStart,
        ];
    }
}
