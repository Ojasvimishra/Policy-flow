<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Services\MockInsurer\MockInsurerService;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles uncertain, in-flight, or timed-out policy requests
 * against authoritative provider-side records.
 */
class ReconciliationService
{
    public function __construct(
        protected MockInsurerService $mockInsurer,
        protected PolicyStateTransitionService $transitionService
    ) {}

    /**
     * Run reconciliation over pending or uncertain requests.
     *
     * @param  int  $limit  Maximum number of records to process in one pass.
     * @return array{
     *     examined: int,
     *     reconciled: int,
     *     still_pending: int,
     *     safe_to_retry: int,
     *     failed: int,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function reconcile(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));

        $stalePolicies = PolicyRequest::whereIn('status', [
            PolicyState::PENDING_CONFIRMATION->value,
            PolicyState::NEEDS_RECONCILIATION->value,
        ])
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $stats = [
            'examined' => 0,
            'reconciled' => 0,
            'still_pending' => 0,
            'safe_to_retry' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach ($stalePolicies as $policy) {
            $stats['examined']++;

            $result = DB::transaction(function () use ($policy) {
                /** @var PolicyRequest $locked */
                $locked = PolicyRequest::where('id', $policy->id)->lockForUpdate()->first();
                if ($locked === null) {
                    return ['status' => 'SKIPPED', 'message' => 'Policy deleted or locked'];
                }

                // If already issued concurrently, do nothing
                if ($locked->isIssued()) {
                    return [
                        'status' => 'ALREADY_ISSUED',
                        'policy_id' => $locked->id,
                        'policy_number' => $locked->policy_number,
                    ];
                }

                // Check provider side status
                $providerRecord = $this->mockInsurer->getStatus($locked->client_request_id);

                if ($providerRecord !== null) {
                    if ($providerRecord->status === 'ISSUED') {
                        $fromState = $locked->status;
                        $toState = PolicyState::ISSUED;

                        $this->transitionService->validateTransition($fromState, $toState, $locked->id);

                        $locked->status = $toState;
                        $locked->policy_number = $providerRecord->policy_number;
                        $locked->provider_reference = $providerRecord->provider_reference;
                        $locked->save();

                        PolicyStateHistory::create([
                            'policy_request_id' => $locked->id,
                            'from_state' => $fromState->value,
                            'to_state' => $toState->value,
                            'trigger' => 'RECONCILIATION_MATCHED_ISSUED',
                            'reason' => "Authoritative insurer record found for {$locked->client_request_id}; reconciled to ISSUED ({$providerRecord->policy_number})",
                        ]);

                        return [
                            'status' => 'RECONCILED',
                            'policy_id' => $locked->id,
                            'policy_number' => $locked->policy_number,
                            'provider_reference' => $locked->provider_reference,
                        ];
                    }

                    // Provider record is PENDING
                    return [
                        'status' => 'STILL_PENDING',
                        'policy_id' => $locked->id,
                        'message' => 'Provider status is PENDING. Awaiting callback or next reconciliation cycle.',
                    ];
                }

                // Provider has NO record of this client_request_id.
                // This definitively confirms the issuance never occurred on the provider.
                $fromState = $locked->status;
                $toState = PolicyState::FAILED;

                $this->transitionService->validateTransition($fromState, $toState, $locked->id);

                $locked->status = $toState;
                $locked->save();

                PolicyStateHistory::create([
                    'policy_request_id' => $locked->id,
                    'from_state' => $fromState->value,
                    'to_state' => $toState->value,
                    'trigger' => 'RECONCILIATION_NOT_FOUND',
                    'reason' => "No provider record existed for {$locked->client_request_id}. Definitively marked FAILED for safe retry.",
                ]);

                return [
                    'status' => 'SAFE_TO_RETRY',
                    'policy_id' => $locked->id,
                    'message' => 'Definitively unissued by provider. Transitioned to FAILED; safe for re-submission.',
                ];
            });

            $stats['items'][] = $result;

            match ($result['status'] ?? '') {
                'RECONCILED' => $stats['reconciled']++,
                'STILL_PENDING' => $stats['still_pending']++,
                'SAFE_TO_RETRY' => $stats['safe_to_retry']++,
                'ALREADY_ISSUED' => null,
                default => $stats['failed']++,
            };
        }

        return $stats;
    }
}
