<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Models\ProviderEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Handles provider webhook callbacks with strict deduplication
 * and protection against downgrading terminal ISSUED states.
 */
class ProviderCallbackService
{
    public function __construct(
        protected PolicyStateTransitionService $transitionService
    ) {}

    /**
     * Process an incoming webhook callback from an insurer provider.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function processCallback(array $data): array
    {
        $provider = (string) ($data['provider'] ?? 'mock_insurer');
        $eventId = (string) ($data['event_id'] ?? '');
        $eventType = (string) ($data['event_type'] ?? 'POLICY_ISSUED');
        $clientRequestId = isset($data['client_request_id']) ? (string) $data['client_request_id'] : null;
        $providerRef = isset($data['provider_reference']) ? (string) $data['provider_reference'] : null;
        $policyNumber = isset($data['policy_number']) ? (string) $data['policy_number'] : null;
        $rawPayload = (array) ($data['payload'] ?? $data);

        if ($eventId === '') {
            throw new InvalidArgumentException("Missing required 'event_id' in provider callback payload.", 422);
        }

        // 1. Deduplication check: Has this event already been persisted?
        $existingEvent = ProviderEvent::where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();

        if ($existingEvent !== null) {
            return [
                'status' => 'DUPLICATE_ACKNOWLEDGED',
                'event_id' => $eventId,
                'provider' => $provider,
                'idempotent_replay' => true,
                'policy_request_id' => $existingEvent->policy_request_id,
                'message' => 'Event already processed. Duplicate delivery acknowledged without side effects.',
            ];
        }

        // 2. Locate matching PolicyRequest
        $policy = null;
        if ($clientRequestId !== null) {
            $policy = PolicyRequest::where('client_request_id', $clientRequestId)->first();
        }
        if ($policy === null && $providerRef !== null) {
            $policy = PolicyRequest::where('provider_reference', $providerRef)->first();
        }

        // 3. Process and persist within an atomic transaction
        try {
            return DB::transaction(function () use (
                $provider,
                $eventId,
                $eventType,
                $clientRequestId,
                $providerRef,
                $policyNumber,
                $rawPayload,
                $policy
            ) {
                // Create ProviderEvent record (enforcing DB constraint on provider + event_id)
                $event = ProviderEvent::create([
                    'provider' => $provider,
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'policy_request_id' => $policy?->id,
                    'provider_reference' => $providerRef,
                    'payload' => $rawPayload,
                    'processed' => false,
                    'processed_at' => null,
                ]);

                // If policy is found, evaluate state transition
                if ($policy !== null) {
                    $lockedPolicy = PolicyRequest::where('id', $policy->id)->lockForUpdate()->first();

                    if ($lockedPolicy !== null) {
                        // Invariant: NEVER mutate or downgrade an already ISSUED policy
                        if ($lockedPolicy->isIssued()) {
                            $event->update([
                                'processed' => true,
                                'processed_at' => now(),
                            ]);

                            return [
                                'status' => 'SUCCESS_UNCHANGED',
                                'event_id' => $eventId,
                                'provider' => $provider,
                                'policy_request_id' => $lockedPolicy->id,
                                'current_status' => PolicyState::ISSUED->value,
                                'idempotent_replay' => false,
                                'message' => 'Policy already confirmed ISSUED. Webhook acknowledged without mutation.',
                            ];
                        }

                        // Determine event outcome
                        $statusPayload = $rawPayload['status'] ?? ($eventType === 'POLICY_ISSUED' ? 'ISSUED' : 'FAILED');

                        if (strtoupper((string) $statusPayload) === 'ISSUED') {
                            $fromState = $lockedPolicy->status;
                            $toState = PolicyState::ISSUED;

                            $this->transitionService->validateTransition($fromState, $toState, $lockedPolicy->id);

                            $lockedPolicy->status = $toState;
                            if ($policyNumber !== null && $lockedPolicy->policy_number === null) {
                                $lockedPolicy->policy_number = $policyNumber;
                            }
                            if ($providerRef !== null && $lockedPolicy->provider_reference === null) {
                                $lockedPolicy->provider_reference = $providerRef;
                            }
                            $lockedPolicy->save();

                            PolicyStateHistory::create([
                                'policy_request_id' => $lockedPolicy->id,
                                'from_state' => $fromState->value,
                                'to_state' => $toState->value,
                                'trigger' => 'PROVIDER_WEBHOOK_CALLBACK',
                                'reason' => "Confirmed via provider event '{$eventId}' ({$eventType})",
                            ]);

                            $event->update([
                                'processed' => true,
                                'processed_at' => now(),
                            ]);

                            return [
                                'status' => 'PROCESSED_ISSUED',
                                'event_id' => $eventId,
                                'provider' => $provider,
                                'policy_request_id' => $lockedPolicy->id,
                                'policy_number' => $lockedPolicy->policy_number,
                                'provider_reference' => $lockedPolicy->provider_reference,
                                'idempotent_replay' => false,
                                'message' => 'Policy state successfully updated to ISSUED via provider callback.',
                            ];
                        }

                        // Failure event
                        $fromState = $lockedPolicy->status;
                        $toState = PolicyState::FAILED;
                        $this->transitionService->validateTransition($fromState, $toState, $lockedPolicy->id);

                        $lockedPolicy->status = $toState;
                        $lockedPolicy->save();

                        PolicyStateHistory::create([
                            'policy_request_id' => $lockedPolicy->id,
                            'from_state' => $fromState->value,
                            'to_state' => $toState->value,
                            'trigger' => 'PROVIDER_WEBHOOK_FAILURE',
                            'reason' => "Provider reported failure in event '{$eventId}'",
                        ]);

                        $event->update([
                            'processed' => true,
                            'processed_at' => now(),
                        ]);

                        return [
                            'status' => 'PROCESSED_FAILED',
                            'event_id' => $eventId,
                            'provider' => $provider,
                            'policy_request_id' => $lockedPolicy->id,
                            'idempotent_replay' => false,
                            'message' => 'Policy state updated to FAILED via provider callback.',
                        ];
                    }
                }

                // If policy was not found, record event as unassociated for audit/investigation
                $event->update([
                    'processed' => false,
                ]);

                return [
                    'status' => 'EVENT_STORED_UNASSOCIATED',
                    'event_id' => $eventId,
                    'provider' => $provider,
                    'policy_request_id' => null,
                    'idempotent_replay' => false,
                    'message' => 'Event stored successfully but no matching policy request was identified.',
                ];
            });
        } catch (QueryException $e) {
            // Check for unique key constraint collision (concurrent duplicate delivery)
            if (str_contains($e->getMessage(), 'uq_provider_events_provider_event') ||
                str_contains($e->getMessage(), 'provider_events_provider_event_id_unique') ||
                str_contains($e->getMessage(), 'UNIQUE constraint failed: provider_events.provider, provider_events.event_id')
            ) {
                return [
                    'status' => 'DUPLICATE_ACKNOWLEDGED',
                    'event_id' => $eventId,
                    'provider' => $provider,
                    'idempotent_replay' => true,
                    'message' => 'Event already persisted concurrently. Duplicate acknowledged.',
                ];
            }

            throw $e;
        }
    }
}
