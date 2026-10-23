<?php

declare(strict_types=1);

namespace App\Services\MockInsurer;

use App\Exceptions\InsurerTemporaryFailureException;
use App\Exceptions\InsurerTimeoutException;
use App\Models\MockInsurerPolicy;
use Illuminate\Support\Str;

/**
 * Deterministic Mock Insurer Subsystem.
 * Simulates external provider issuance, socket timeouts, temporary gateway failures,
 * and asynchronous callback scenarios using isolated persistence.
 */
class MockInsurerService
{
    public const SCENARIO_SUCCESS = 'SUCCESS';

    public const SCENARIO_TEMPORARY_FAILURE = 'TEMPORARY_FAILURE';

    public const SCENARIO_TIMEOUT_AFTER_ISSUE = 'TIMEOUT_AFTER_ISSUE';

    public const SCENARIO_DELAYED_CALLBACK = 'DELAYED_CALLBACK';

    public const SCENARIO_DUPLICATE_CALLBACK = 'DUPLICATE_CALLBACK';

    /**
     * Process an issuance request under a specified deterministic scenario.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws InsurerTemporaryFailureException
     * @throws InsurerTimeoutException
     */
    public function issuePolicy(array $payload, string $scenario = self::SCENARIO_SUCCESS): array
    {
        $clientRequestId = (string) $payload['client_request_id'];
        $agentId = (string) ($payload['agent_id'] ?? 'AGENT-UNKNOWN');
        $sumInsured = (string) ($payload['sum_insured'] ?? '100000.00');
        $premium = (string) ($payload['final_premium'] ?? '12.00');

        $normalizedScenario = strtoupper(trim($scenario));

        switch ($normalizedScenario) {
            case self::SCENARIO_TEMPORARY_FAILURE:
                // Does NOT persist any policy record; simulates a 503 gateway failure
                throw new InsurerTemporaryFailureException(
                    "Simulated temporary gateway failure for request '{$clientRequestId}'. Retriable.",
                    503,
                    null,
                    true
                );

            case self::SCENARIO_TIMEOUT_AFTER_ISSUE:
                // Critical Hazard: Provider issues policy internally, but connection drops before response
                $providerPolicy = $this->findOrCreateProviderPolicy(
                    $clientRequestId,
                    $agentId,
                    $sumInsured,
                    $premium,
                    'ISSUED'
                );

                throw new InsurerTimeoutException(
                    "Connection timed out while awaiting provider response for '{$clientRequestId}'.",
                    504,
                    null,
                    $clientRequestId,
                    $providerPolicy->provider_reference
                );

            case self::SCENARIO_DELAYED_CALLBACK:
                // Provider accepts the request as PENDING; policy will be confirmed via later webhook
                $providerPolicy = $this->findOrCreateProviderPolicy(
                    $clientRequestId,
                    $agentId,
                    $sumInsured,
                    $premium,
                    'PENDING'
                );

                return [
                    'status' => 'PENDING',
                    'provider' => 'mock_insurer',
                    'client_request_id' => $clientRequestId,
                    'provider_reference' => $providerPolicy->provider_reference,
                    'policy_number' => null,
                    'message' => 'Policy intake accepted by insurer. Awaiting asynchronous confirmation callback.',
                ];

            case self::SCENARIO_DUPLICATE_CALLBACK:
            case self::SCENARIO_SUCCESS:
            default:
                // Standard happy path: Policy created and immediately confirmed
                $providerPolicy = $this->findOrCreateProviderPolicy(
                    $clientRequestId,
                    $agentId,
                    $sumInsured,
                    $premium,
                    'ISSUED'
                );

                return [
                    'status' => 'SUCCESS',
                    'provider' => 'mock_insurer',
                    'client_request_id' => $clientRequestId,
                    'provider_reference' => $providerPolicy->provider_reference,
                    'policy_number' => $providerPolicy->policy_number,
                    'issued_at' => now()->toIso8601String(),
                    'message' => 'Policy successfully issued by insurer.',
                ];
        }
    }

    /**
     * Retrieve provider issuance status for reconciliation.
     */
    public function getStatus(string $clientRequestId): ?MockInsurerPolicy
    {
        return MockInsurerPolicy::where('client_request_id', trim($clientRequestId))->first();
    }

    /**
     * Generate a deterministic webhook payload for callback testing.
     *
     * @return array<string, mixed>
     */
    public function buildCallbackPayload(
        string $clientRequestId,
        string $eventId = '',
        string $eventType = 'POLICY_ISSUED'
    ): array {
        $policy = $this->getStatus($clientRequestId);

        $id = $eventId !== '' ? $eventId : 'EVT-MOCK-'.strtoupper(Str::random(10));
        $polNumber = $policy?->policy_number ?? ('POL-MOCK-'.strtoupper(Str::random(8)));
        $provRef = $policy?->provider_reference ?? ('PRV-MOCK-'.strtoupper(Str::random(12)));

        return [
            'provider' => 'mock_insurer',
            'event_id' => $id,
            'event_type' => $eventType,
            'client_request_id' => $clientRequestId,
            'provider_reference' => $provRef,
            'policy_number' => $polNumber,
            'timestamp' => now()->toIso8601String(),
            'payload' => [
                'status' => 'ISSUED',
                'sum_insured' => $policy?->sum_insured ?? '300000.00',
                'premium' => $policy?->premium_charged ?? '225.00',
            ],
        ];
    }

    /**
     * Find or create an isolated MockInsurerPolicy.
     */
    protected function findOrCreateProviderPolicy(
        string $clientRequestId,
        string $agentId,
        string $sumInsured,
        string $premium,
        string $status
    ): MockInsurerPolicy {
        $existing = MockInsurerPolicy::where('client_request_id', $clientRequestId)->first();
        if ($existing !== null) {
            return $existing;
        }

        // Generate stable, unique policy numbers and provider references
        $hash = substr(md5($clientRequestId.$agentId), 0, 8);
        $policyNumber = 'POL-MOCK-'.strtoupper($hash);
        $providerRef = 'PRV-'.strtoupper(Str::random(12));

        return MockInsurerPolicy::create([
            'client_request_id' => $clientRequestId,
            'agent_id' => $agentId,
            'policy_number' => $policyNumber,
            'provider_reference' => $providerRef,
            'status' => $status,
            'sum_insured' => $sumInsured,
            'premium_charged' => $premium,
            'metadata' => [
                'issued_by' => 'MockInsurerEngine',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
