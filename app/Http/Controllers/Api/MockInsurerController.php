<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\InsurerTemporaryFailureException;
use App\Exceptions\InsurerTimeoutException;
use App\Services\MockInsurer\MockInsurerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MockInsurerController extends Controller
{
    public function __construct(
        protected MockInsurerService $mockInsurer
    ) {}

    /**
     * POST /api/mock-insurer/issue
     * Simulates receiving an issuance request under a specified scenario.
     */
    public function issue(Request $request): JsonResponse
    {
        $scenario = $request->header('X-Mock-Scenario')
            ?? $request->input('scenario')
            ?? MockInsurerService::SCENARIO_SUCCESS;

        $payload = $request->all();

        if (empty($payload['client_request_id'])) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_PROVIDER_PAYLOAD',
                    'message' => 'client_request_id is required by the mock insurer.',
                ],
            ], 422);
        }

        try {
            $result = $this->mockInsurer->issuePolicy($payload, (string) $scenario);
            $statusCode = ($result['status'] === 'PENDING') ? 202 : 200;

            return response()->json($result, $statusCode);
        } catch (InsurerTemporaryFailureException $e) {
            return response()->json([
                'status' => 'TEMPORARY_FAILURE',
                'provider' => 'mock_insurer',
                'error' => $e->getMessage(),
                'retriable' => true,
            ], 503);
        } catch (InsurerTimeoutException $e) {
            return response()->json([
                'status' => 'TIMEOUT',
                'provider' => 'mock_insurer',
                'error' => $e->getMessage(),
                'retriable' => false,
            ], 504);
        }
    }

    /**
     * GET /api/mock-insurer/status/{client_request_id}
     * Retrieves the simulated provider's persisted status for reconciliation.
     */
    public function status(string $client_request_id): JsonResponse
    {
        $policy = $this->mockInsurer->getStatus($client_request_id);

        if ($policy === null) {
            return response()->json([
                'status' => 'NOT_FOUND',
                'client_request_id' => $client_request_id,
                'message' => 'No policy record found on insurer side for this client_request_id.',
            ], 404);
        }

        return response()->json([
            'status' => $policy->status,
            'client_request_id' => $policy->client_request_id,
            'policy_number' => $policy->policy_number,
            'provider_reference' => $policy->provider_reference,
            'sum_insured' => $policy->sum_insured,
            'premium_charged' => $policy->premium_charged,
            'created_at' => $policy->created_at?->toIso8601String(),
        ], 200);
    }
}
