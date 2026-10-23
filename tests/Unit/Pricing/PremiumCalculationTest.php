<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Services\PremiumCalculationService;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PremiumCalculationTest extends TestCase
{
    protected PremiumCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PremiumCalculationService;
    }

    /**
     * Requirement 1: Authoritative Reference Calculation.
     * Plan: PLUS (20.00), 5 days, Age 28 (1.00), INTERNATIONAL (1.80), Coverage 300,000 (1.25)
     * Expected: 20 * 5 * 1.00 * 1.80 * 1.25 = 225.00
     */
    public function test_reference_premium_calculation_equals_exact_225_inr(): void
    {
        $snapshot = $this->service->calculate(
            planCode: 'PLUS',
            sumInsured: 300000,
            destination: 'INTERNATIONAL',
            dateOfBirth: '1998-04-10',
            tripStart: '2026-11-10',
            tripEnd: '2026-11-14'
        );

        $this->assertSame(5, $snapshot->travelDays);
        $this->assertSame('20.00', $snapshot->baseDailyRate);
        $this->assertSame('1.00', $snapshot->ageMultiplier);
        $this->assertSame('1.80', $snapshot->destinationMultiplier);
        $this->assertSame('1.25', $snapshot->coverageMultiplier);
        $this->assertSame('225.00', $snapshot->finalPremium);
    }

    /**
     * Requirement 2: All three plan base daily rates.
     */
    public function test_all_base_rates(): void
    {
        // BASIC: 12.00 * 1 day * 1.00 * 1.00 * 1.00 = 12.00
        $basic = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '1995-01-01', '2026-11-01', '2026-11-01');
        $this->assertSame('12.00', $basic->baseDailyRate);
        $this->assertSame('12.00', $basic->finalPremium);

        // PLUS: 20.00 * 1 day * 1.00 * 1.00 * 1.00 = 20.00
        $plus = $this->service->calculate('PLUS', 100000, 'DOMESTIC', '1995-01-01', '2026-11-01', '2026-11-01');
        $this->assertSame('20.00', $plus->baseDailyRate);
        $this->assertSame('20.00', $plus->finalPremium);

        // PREMIUM: 30.00 * 1 day * 1.00 * 1.00 * 1.00 = 30.00
        $premium = $this->service->calculate('PREMIUM', 100000, 'DOMESTIC', '1995-01-01', '2026-11-01', '2026-11-01');
        $this->assertSame('30.00', $premium->baseDailyRate);
        $this->assertSame('30.00', $premium->finalPremium);
    }

    /**
     * Requirement 3: Age boundaries: 17, 18, 60, 61, 70, 71.
     * Trip Start: 2026-10-01.
     */
    public function test_age_multipliers_across_all_boundaries(): void
    {
        $tripStart = Carbon::parse('2026-10-01');

        // Age 17 (DOB 2009-10-01): 0-17 band -> 1.10
        $this->assertSame('1.10', $this->service->getAgeMultiplier(17));
        $snap17 = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '2009-10-01', '2026-10-01', '2026-10-01');
        $this->assertSame(17, $snap17->ageAtTripStart);
        $this->assertSame('1.10', $snap17->ageMultiplier);

        // Age 18 (DOB 2008-10-01): 18-60 band -> 1.00
        $this->assertSame('1.00', $this->service->getAgeMultiplier(18));
        $snap18 = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '2008-10-01', '2026-10-01', '2026-10-01');
        $this->assertSame(18, $snap18->ageAtTripStart);
        $this->assertSame('1.00', $snap18->ageMultiplier);

        // Age 60 (DOB 1966-10-01): 18-60 band -> 1.00
        $this->assertSame('1.00', $this->service->getAgeMultiplier(60));
        $snap60 = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '1966-10-01', '2026-10-01', '2026-10-01');
        $this->assertSame(60, $snap60->ageAtTripStart);
        $this->assertSame('1.00', $snap60->ageMultiplier);

        // Age 61 (DOB 1965-10-01): 61-70 band -> 1.50
        $this->assertSame('1.50', $this->service->getAgeMultiplier(61));
        $snap61 = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '1965-10-01', '2026-10-01', '2026-10-01');
        $this->assertSame(61, $snap61->ageAtTripStart);
        $this->assertSame('1.50', $snap61->ageMultiplier);

        // Age 70 (DOB 1956-10-01): 61-70 band -> 1.50
        $this->assertSame('1.50', $this->service->getAgeMultiplier(70));
        $snap70 = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '1956-10-01', '2026-10-01', '2026-10-01');
        $this->assertSame(70, $snap70->ageAtTripStart);
        $this->assertSame('1.50', $snap70->ageMultiplier);

        // Age 71 (DOB 1955-10-01): 71+ band -> 2.00
        $this->assertSame('2.00', $this->service->getAgeMultiplier(71));
        $snap71 = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '1955-10-01', '2026-10-01', '2026-10-01');
        $this->assertSame(71, $snap71->ageAtTripStart);
        $this->assertSame('2.00', $snap71->ageMultiplier);
    }

    /**
     * Requirement 4: Age calculated on trip_start, NOT current date.
     * Birthday falls between booking date and trip start date.
     */
    public function test_age_calculated_strictly_on_trip_start_date(): void
    {
        // DOB: 2008-11-05
        // Trip 1: 2026-11-04 (Day before 18th birthday) -> Age 17, multiplier 1.10
        $snap17 = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '2008-11-05', '2026-11-04', '2026-11-04');
        $this->assertSame(17, $snap17->ageAtTripStart);
        $this->assertSame('1.10', $snap17->ageMultiplier);

        // Trip 2: 2026-11-05 (Exact 18th birthday) -> Age 18, multiplier 1.00
        $snap18 = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '2008-11-05', '2026-11-05', '2026-11-05');
        $this->assertSame(18, $snap18->ageAtTripStart);
        $this->assertSame('1.00', $snap18->ageMultiplier);
    }

    /**
     * Requirement 5: Destination multipliers.
     */
    public function test_destination_multipliers(): void
    {
        $dom = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '1990-01-01', '2026-05-01', '2026-05-01');
        $this->assertSame('1.00', $dom->destinationMultiplier);

        $intl = $this->service->calculate('BASIC', 100000, 'INTERNATIONAL', '1990-01-01', '2026-05-01', '2026-05-01');
        $this->assertSame('1.80', $intl->destinationMultiplier);
    }

    /**
     * Requirement 6: Coverage multipliers.
     */
    public function test_coverage_multipliers(): void
    {
        $cov1 = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '1990-01-01', '2026-05-01', '2026-05-01');
        $this->assertSame('1.00', $cov1->coverageMultiplier);

        $cov3 = $this->service->calculate('BASIC', 300000, 'DOMESTIC', '1990-01-01', '2026-05-01', '2026-05-01');
        $this->assertSame('1.25', $cov3->coverageMultiplier);

        $cov5 = $this->service->calculate('BASIC', 500000, 'DOMESTIC', '1990-01-01', '2026-05-01', '2026-05-01');
        $this->assertSame('1.50', $cov5->coverageMultiplier);
    }

    /**
     * Requirement 7: Travel days inclusive calculation and same-day trip.
     */
    public function test_inclusive_travel_days_and_same_day(): void
    {
        // Same-day trip = 1 day
        $sameDay = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '1990-01-01', '2026-06-15', '2026-06-15');
        $this->assertSame(1, $sameDay->travelDays);

        // 3 inclusive days (June 10 to June 12)
        $threeDays = $this->service->calculate('BASIC', 100000, 'DOMESTIC', '1990-01-01', '2026-06-10', '2026-06-12');
        $this->assertSame(3, $threeDays->travelDays);
    }

    /**
     * Requirement 8: Invalid inputs throw InvalidArgumentException.
     */
    public function test_invalid_plan_code_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported plan code: 'ULTRA'");
        $this->service->calculate('ULTRA', 100000, 'DOMESTIC', '1990-01-01', '2026-06-01', '2026-06-05');
    }

    public function test_invalid_destination_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported destination: 'SPACE'");
        $this->service->calculate('BASIC', 100000, 'SPACE', '1990-01-01', '2026-06-01', '2026-06-05');
    }

    public function test_invalid_sum_insured_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported sum insured: '250000'");
        $this->service->calculate('BASIC', 250000, 'DOMESTIC', '1990-01-01', '2026-06-01', '2026-06-05');
    }

    public function test_trip_end_before_trip_start_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trip end date (2026-05-31) cannot be earlier than trip start date (2026-06-01)');
        $this->service->calculate('BASIC', 100000, 'DOMESTIC', '1990-01-01', '2026-06-01', '2026-05-31');
    }

    public function test_date_of_birth_after_trip_start_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be after trip start date');
        $this->service->calculate('BASIC', 100000, 'DOMESTIC', '2027-01-01', '2026-06-01', '2026-06-05');
    }
}
