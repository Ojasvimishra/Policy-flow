<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PricingSnapshot;
use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates policy intake, deterministic pricing calculation,
 * and concurrency-safe idempotency verification.
 */
class PolicyIntakeService
{
    public function __construct(
        protected PremiumCalculationService $pricingService
    ) {}

    /**
     * Process an incoming policy intake request.
     * Guarantees that repeated submissions with identical (agent_id, client_request_id)
     * return the original canonical record without recalculating or overwriting the premium snapshot.
     *
     * @param array<string, mixed> $data Validated intake attributes
     * @return array{policy: PolicyRequest, is_replay: bool, payload_mismatch: bool, snapshot: PricingSnapshot|null}
     */
    public function processIntake(array $data): array
    {
        $agentId = (string) $data['agent_id'];
        $clientRequestId = (string) $data['client_request_id'];

        // Step 1: Pre-check for existing record
        $existing = PolicyRequest::where('agent_id', $agentId)
            ->where('client_request_id', $clientRequestId)
            ->first();

        if ($existing !== null) {
            $mismatch = $this->hasPayloadMismatch($existing, $data);
            if ($mismatch) {
                Log::warning('Policy intake idempotency key reused with mismatched payload', [
                    'agent_id' => $agentId,
                    'client_request_id' => $clientRequestId,
                    'existing_id' => $existing->id,
                ]);
            }

            return [
                'policy' => $existing,
                'is_replay' => true,
                'payload_mismatch' => $mismatch,
                'snapshot' => null,
            ];
        }

        // Step 2: Calculate deterministic pricing snapshot
        $snapshot = $this->pricingService->calculate(
            planCode: (string) $data['plan_code'],
            sumInsured: $data['sum_insured'],
            destination: (string) $data['destination'],
            dateOfBirth: (string) $data['date_of_birth'],
            tripStart: (string) $data['trip_start'],
            tripEnd: (string) $data['trip_end']
        );

        // Step 3: Atomic database transaction with race-condition catch
        try {
            $policy = DB::transaction(function () use ($data, $snapshot) {
                $record = new PolicyRequest([
                    'agent_id' => $data['agent_id'],
                    'client_request_id' => $data['client_request_id'],
                    'customer_name' => $data['customer_name'],
                    'date_of_birth' => $data['date_of_birth'],
                    'destination' => $data['destination'],
                    'trip_start' => $data['trip_start'],
                    'trip_end' => $data['trip_end'],
                    'plan_code' => strtoupper((string) $data['plan_code']),
                    'sum_insured' => (string) $data['sum_insured'],
                ]);

                // Store immutable calculated pricing snapshot
                $record->travel_days = $snapshot->travelDays;
                $record->base_daily_rate = $snapshot->baseDailyRate;
                $record->age_multiplier = $snapshot->ageMultiplier;
                $record->destination_multiplier = $snapshot->destinationMultiplier;
                $record->coverage_multiplier = $snapshot->coverageMultiplier;
                $record->final_premium = $snapshot->finalPremium;
                $record->status = PolicyState::RECEIVED;

                $record->save();

                // Append initial state audit ledger entry
                PolicyStateHistory::create([
                    'policy_request_id' => $record->id,
                    'from_state' => 'NONE',
                    'to_state' => PolicyState::RECEIVED->value,
                    'trigger' => 'POLICY_INTAKE',
                    'reason' => 'Initial policy intake and pricing snapshot recorded',
                    'metadata' => [
                        'travel_days' => $snapshot->travelDays,
                        'final_premium' => $snapshot->finalPremium,
                        'age_at_trip_start' => $snapshot->ageAtTripStart,
                    ],
                ]);

                return $record;
            });

            return [
                'policy' => $policy,
                'is_replay' => false,
                'payload_mismatch' => false,
                'snapshot' => $snapshot,
            ];
        } catch (QueryException $e) {
            // Step 4: Handle concurrent race condition when two requests insert simultaneously
            if ($this->isUniqueConstraintViolation($e)) {
                $canonical = PolicyRequest::where('agent_id', $agentId)
                    ->where('client_request_id', $clientRequestId)
                    ->first();

                if ($canonical !== null) {
                    return [
                        'policy' => $canonical,
                        'is_replay' => true,
                        'payload_mismatch' => $this->hasPayloadMismatch($canonical, $data),
                        'snapshot' => null,
                    ];
                }
            }

            // Re-throw any unrelated database error
            throw $e;
        }
    }

    /**
     * Detect if an incoming repeated submission has differing core parameters.
     *
     * @param PolicyRequest $policy
     * @param array<string, mixed> $data
     * @return bool
     */
    protected function hasPayloadMismatch(PolicyRequest $policy, array $data): bool
    {
        if ($policy->customer_name !== (string) $data['customer_name']) {
            return true;
        }

        if (strtoupper($policy->plan_code) !== strtoupper((string) $data['plan_code'])) {
            return true;
        }

        if ((int) $policy->sum_insured !== (int) $data['sum_insured']) {
            return true;
        }

        if ($policy->destination !== (string) $data['destination']) {
            return true;
        }

        if ($policy->trip_start?->toDateString() !== (string) $data['trip_start']) {
            return true;
        }

        if ($policy->trip_end?->toDateString() !== (string) $data['trip_end']) {
            return true;
        }

        return false;
    }

    /**
     * Database-aware check for unique constraint collisions across PostgreSQL, MySQL, and SQLite.
     */
    protected function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->getCode();
        $message = strtolower($e->getMessage());

        // PostgreSQL: 23505 (unique_violation)
        if ($sqlState === '23505') {
            return true;
        }

        // MySQL: 1062 (ER_DUP_ENTRY) or 23000
        if ($sqlState === '23000' && (str_contains($message, 'duplicate entry') || str_contains($message, '1062'))) {
            return true;
        }

        // SQLite: 19 / 2067 (SQLITE_CONSTRAINT_UNIQUE)
        if (str_contains($message, 'unique constraint failed') || str_contains($message, 'sqlite_constraint_unique')) {
            return true;
        }

        return false;
    }
}
