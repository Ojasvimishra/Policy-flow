<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PolicyState;
use App\Exceptions\InsurerTemporaryFailureException;
use App\Exceptions\InsurerTimeoutException;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Services\MockInsurer\MockInsurerService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates policy issuance requests to insurers.
 * Manages atomic reservation, sequential attempt records,
 * and defensive handling of provider timeouts without blind reissue.
 */
class IssuanceOrchestrator
{
    public function __construct(
        protected MockInsurerService $mockInsurer,
        protected PolicyStateTransitionService $transitionService
    ) {}

    /**
     * Submit a policy request for issuance.
     *
     * @param  string|null  $scenario  Test scenario override (e.g. TIMEOUT_AFTER_ISSUE)
     * @return array<string, mixed>
     */
    public function submit(int|string $policyId, ?string $scenario = null): array
    {
        $policy = PolicyRequest::find($policyId);
        if ($policy === null) {
            throw new DomainException("Policy request #{$policyId} not found.", 404);
        }

        // Idempotency check: Already issued policies are immutable
        if ($policy->isIssued()) {
            return [
                'success' => true,
                'status' => PolicyState::ISSUED->value,
                'policy_id' => $policy->id,
                'client_request_id' => $policy->client_request_id,
                'policy_number' => $policy->policy_number,
                'provider_reference' => $policy->provider_reference,
                'final_premium' => $policy->final_premium,
                'idempotent_replay' => true,
                'message' => 'Policy already confirmed issued. No further provider calls dispatched.',
            ];
        }

        // Defensive check: Do not submit if in an uncertain pending state without reconciliation
        if ($policy->status === PolicyState::PENDING_CONFIRMATION) {
            return [
                'success' => false,
                'status' => PolicyState::PENDING_CONFIRMATION->value,
                'policy_id' => $policy->id,
                'client_request_id' => $policy->client_request_id,
                'message' => 'Policy is awaiting confirmation from insurer. Run reconciliation before retrying.',
                'requires_reconciliation' => true,
            ];
        }

        // Step 1: Short reservation transaction
        $attempt = DB::transaction(function () use ($policy, $scenario) {
            // Lock row for update to prevent concurrent double-flight submissions
            $lockedPolicy = PolicyRequest::where('id', $policy->id)->lockForUpdate()->firstOrFail();

            if ($lockedPolicy->isIssued()) {
                return null;
            }

            // Enforce state transition pipeline: RECEIVED -> VALIDATED -> SUBMITTED
            if ($lockedPolicy->status === PolicyState::RECEIVED) {
                $this->transitionService->validateTransition($lockedPolicy->status, PolicyState::VALIDATED, $lockedPolicy->id);
                $lockedPolicy->status = PolicyState::VALIDATED;
                $lockedPolicy->save();

                PolicyStateHistory::create([
                    'policy_request_id' => $lockedPolicy->id,
                    'from_state' => PolicyState::RECEIVED->value,
                    'to_state' => PolicyState::VALIDATED->value,
                    'trigger' => 'VALIDATION_VERIFIED',
                    'reason' => 'Policy validated prior to insurer submission',
                ]);
            }

            $fromState = $lockedPolicy->status;
            $this->transitionService->validateTransition($fromState, PolicyState::SUBMITTED, $lockedPolicy->id);

            $lockedPolicy->status = PolicyState::SUBMITTED;
            $lockedPolicy->attempt_count += 1;
            $lockedPolicy->save();

            PolicyStateHistory::create([
                'policy_request_id' => $lockedPolicy->id,
                'from_state' => $fromState->value,
                'to_state' => PolicyState::SUBMITTED->value,
                'trigger' => 'SUBMIT_FOR_ISSUANCE',
                'reason' => 'Dispatched to insurer (attempt #'.$lockedPolicy->attempt_count.')',
            ]);

            return IssuanceAttempt::create([
                'policy_request_id' => $lockedPolicy->id,
                'attempt_number' => $lockedPolicy->attempt_count,
                'provider' => 'mock_insurer',
                'scenario_requested' => $scenario ?? MockInsurerService::SCENARIO_SUCCESS,
                'request_payload' => [
                    'client_request_id' => $lockedPolicy->client_request_id,
                    'agent_id' => $lockedPolicy->agent_id,
                    'customer_name' => $lockedPolicy->customer_name,
                    'destination' => $lockedPolicy->destination,
                    'plan_code' => $lockedPolicy->plan_code,
                    'sum_insured' => $lockedPolicy->sum_insured,
                    'final_premium' => $lockedPolicy->final_premium,
                    'travel_days' => $lockedPolicy->travel_days,
                ],
                'status' => 'IN_FLIGHT',
                'attempted_at' => now(),
            ]);
        });

        if ($attempt === null) {
            // Concurrency catch: Was issued during lock
            $fresh = $policy->fresh();

            return [
                'success' => true,
                'status' => PolicyState::ISSUED->value,
                'policy_id' => $fresh->id,
                'policy_number' => $fresh->policy_number,
                'idempotent_replay' => true,
            ];
        }

        // Step 2: Call provider OUTSIDE the database transaction
        $payload = $attempt->request_payload;
        $chosenScenario = $scenario ?? MockInsurerService::SCENARIO_SUCCESS;

        try {
            $providerResult = $this->mockInsurer->issuePolicy($payload, $chosenScenario);

            // Step 3A: Finalize Success or Pending in a short second transaction
            return DB::transaction(function () use ($policy, $attempt, $providerResult) {
                $locked = PolicyRequest::where('id', $policy->id)->lockForUpdate()->firstOrFail();

                if ($providerResult['status'] === 'PENDING') {
                    $attempt->update([
                        'status' => 'PENDING',
                        'response_status_code' => 202,
                        'response_payload' => $providerResult,
                        'completed_at' => now(),
                    ]);

                    $fromState = $locked->status;
                    $locked->status = PolicyState::PENDING_CONFIRMATION;
                    $locked->provider_reference = $providerResult['provider_reference'];
                    $locked->save();

                    PolicyStateHistory::create([
                        'policy_request_id' => $locked->id,
                        'from_state' => $fromState->value,
                        'to_state' => PolicyState::PENDING_CONFIRMATION->value,
                        'trigger' => 'PROVIDER_PENDING_RESPONSE',
                        'reason' => 'Provider accepted request; awaiting webhook confirmation',
                    ]);

                    return [
                        'success' => true,
                        'status' => PolicyState::PENDING_CONFIRMATION->value,
                        'policy_id' => $locked->id,
                        'client_request_id' => $locked->client_request_id,
                        'provider_reference' => $providerResult['provider_reference'],
                        'message' => 'Policy submission pending provider callback.',
                    ];
                }

                // SUCCESS
                $attempt->update([
                    'status' => 'SUCCESS',
                    'response_status_code' => 200,
                    'response_payload' => $providerResult,
                    'completed_at' => now(),
                ]);

                $fromState = $locked->status;
                $locked->status = PolicyState::ISSUED;
                $locked->policy_number = $providerResult['policy_number'];
                $locked->provider_reference = $providerResult['provider_reference'];
                $locked->save();

                PolicyStateHistory::create([
                    'policy_request_id' => $locked->id,
                    'from_state' => $fromState->value,
                    'to_state' => PolicyState::ISSUED->value,
                    'trigger' => 'PROVIDER_ISSUANCE_SUCCESS',
                    'reason' => 'Successfully issued with policy number: '.$providerResult['policy_number'],
                ]);

                return [
                    'success' => true,
                    'status' => PolicyState::ISSUED->value,
                    'policy_id' => $locked->id,
                    'client_request_id' => $locked->client_request_id,
                    'policy_number' => $locked->policy_number,
                    'provider_reference' => $locked->provider_reference,
                    'final_premium' => $locked->final_premium,
                    'idempotent_replay' => false,
                ];
            });
        } catch (InsurerTimeoutException $e) {
            // Step 3B: Defensive Timeout Handling (Never blind reissue)
            return DB::transaction(function () use ($policy, $attempt, $e) {
                $locked = PolicyRequest::where('id', $policy->id)->lockForUpdate()->firstOrFail();

                $attempt->update([
                    'status' => 'TIMEOUT',
                    'response_status_code' => 504,
                    'error_detail' => $e->getMessage(),
                    'completed_at' => now(),
                ]);

                $fromState = $locked->status;
                $locked->status = PolicyState::PENDING_CONFIRMATION;
                if ($e->providerReference !== null) {
                    $locked->provider_reference = $e->providerReference;
                }
                $locked->save();

                PolicyStateHistory::create([
                    'policy_request_id' => $locked->id,
                    'from_state' => $fromState->value,
                    'to_state' => PolicyState::PENDING_CONFIRMATION->value,
                    'trigger' => 'PROVIDER_SOCKET_TIMEOUT',
                    'reason' => 'Timeout after submission: '.$e->getMessage().'. Transitioned to PENDING_CONFIRMATION for reconciliation.',
                ]);

                return [
                    'success' => false,
                    'status' => PolicyState::PENDING_CONFIRMATION->value,
                    'policy_id' => $locked->id,
                    'client_request_id' => $locked->client_request_id,
                    'error' => 'Provider call timed out. Policy placed in PENDING_CONFIRMATION to prevent duplicate issuance.',
                    'requires_reconciliation' => true,
                ];
            });
        } catch (InsurerTemporaryFailureException $e) {
            // Step 3C: Temporary Failure Handling
            return DB::transaction(function () use ($policy, $attempt, $e) {
                $locked = PolicyRequest::where('id', $policy->id)->lockForUpdate()->firstOrFail();

                $attempt->update([
                    'status' => 'TEMPORARY_FAILURE',
                    'response_status_code' => 503,
                    'error_detail' => $e->getMessage(),
                    'completed_at' => now(),
                ]);

                $fromState = $locked->status;
                $locked->status = PolicyState::FAILED;
                $locked->save();

                PolicyStateHistory::create([
                    'policy_request_id' => $locked->id,
                    'from_state' => $fromState->value,
                    'to_state' => PolicyState::FAILED->value,
                    'trigger' => 'PROVIDER_TEMPORARY_FAILURE',
                    'reason' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'status' => PolicyState::FAILED->value,
                    'policy_id' => $locked->id,
                    'client_request_id' => $locked->client_request_id,
                    'error' => $e->getMessage(),
                    'retriable' => true,
                ];
            });
        }
    }
}
