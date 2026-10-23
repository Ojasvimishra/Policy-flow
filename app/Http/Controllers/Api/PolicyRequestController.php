<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\PolicyState;
use App\Http\Requests\StorePolicyRequest;
use App\Http\Resources\PolicyRequestResource;
use App\Models\PolicyRequest;
use App\Services\IssuanceOrchestrator;
use App\Services\PolicyIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class PolicyRequestController extends Controller
{
    public function __construct(
        protected PolicyIntakeService $intakeService,
        protected IssuanceOrchestrator $orchestrator
    ) {}

    /**
     * POST /api/policy-requests
     * Create a new policy request or return existing request if matching idempotency key.
     */
    public function store(StorePolicyRequest $request): JsonResponse
    {
        $result = $this->intakeService->processIntake($request->validated());

        $isReplay = $result['is_replay'];
        $statusCode = $isReplay ? 200 : 201;

        $meta = [
            'idempotent_replay' => $isReplay,
        ];

        if ($isReplay && $result['payload_mismatch']) {
            $meta['payload_mismatch'] = true;
            $meta['warning'] = 'Idempotency key was reused with differing request attributes. Original request returned.';
        }

        return (new PolicyRequestResource($result['policy']))
            ->additional(['meta' => $meta])
            ->response()
            ->setStatusCode($statusCode);
    }

    /**
     * GET /api/policy-requests/{id}
     * Retrieve a single policy request by identifier.
     */
    public function show(int|string $id): JsonResponse
    {
        $policy = PolicyRequest::with(['attempts', 'stateHistory', 'providerEvents'])->find($id);

        if ($policy === null) {
            return response()->json([
                'error' => [
                    'code' => 'POLICY_NOT_FOUND',
                    'message' => "Policy request #{$id} does not exist.",
                ],
            ], 404);
        }

        return (new PolicyRequestResource($policy))
            ->additional([
                'meta' => [
                    'retrieved_at' => now()->toIso8601String(),
                ],
            ])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * GET /api/policy-requests
     * List and filter policy requests with deterministic pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'status' => ['nullable', 'string', 'in:'.implode(',', PolicyState::values())],
            'agent_id' => ['nullable', 'string', 'max:64'],
            'destination' => ['nullable', 'string', 'in:DOMESTIC,INTERNATIONAL'],
            'plan_code' => ['nullable', 'string', 'in:BASIC,PLUS,PREMIUM,basic,plus,premium'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_FILTER_PARAMETERS',
                    'message' => 'One or more filter parameters are invalid.',
                    'fields' => $validator->errors(),
                ],
            ], 422);
        }

        $query = PolicyRequest::query();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->query('agent_id'));
        }

        if ($request->filled('destination')) {
            $query->where('destination', strtoupper((string) $request->query('destination')));
        }

        if ($request->filled('plan_code')) {
            $query->where('plan_code', strtoupper((string) $request->query('plan_code')));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));

        // Deterministic ordering: latest created first, with ID as stable tie-breaker
        $paginator = $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return PolicyRequestResource::collection($paginator)->response();
    }

    /**
     * POST /api/policy-requests/{id}/submit
     * Dispatches policy request to insurer for issuance.
     */
    public function submit(Request $request, int|string $id): JsonResponse
    {
        $scenario = $request->header('X-Mock-Scenario')
            ?? $request->input('scenario');

        try {
            $result = $this->orchestrator->submit($id, $scenario ? (string) $scenario : null);

            $status = $result['status'] ?? 'UNKNOWN';
            $httpCode = match ($status) {
                'ISSUED' => 200,
                'PENDING_CONFIRMATION' => 202,
                'FAILED' => 503,
                default => 200,
            };

            return response()->json($result, $httpCode);
        } catch (\DomainException $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422;

            return response()->json([
                'error' => [
                    'code' => 'SUBMISSION_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], $code);
        }
    }
}
