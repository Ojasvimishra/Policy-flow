<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OperationsController extends Controller
{
    public function __construct(
        protected ReconciliationService $reconciliationService
    ) {}

    /**
     * GET /api/dashboard/metrics
     * Returns authoritative system metrics directly from the primary database.
     */
    public function metrics(): JsonResponse
    {
        $total = PolicyRequest::count();
        $issued = PolicyRequest::where('status', PolicyState::ISSUED->value)->count();
        $pending = PolicyRequest::where('status', PolicyState::PENDING_CONFIRMATION->value)->count();
        $needsRecon = PolicyRequest::where('status', PolicyState::NEEDS_RECONCILIATION->value)->count();
        $failed = PolicyRequest::where('status', PolicyState::FAILED->value)->count();
        $submitted = PolicyRequest::where('status', PolicyState::SUBMITTED->value)->count();
        $received = PolicyRequest::where('status', PolicyState::RECEIVED->value)->count();
        $validated = PolicyRequest::where('status', PolicyState::VALIDATED->value)->count();

        $totalPremiumIssued = PolicyRequest::where('status', PolicyState::ISSUED->value)->sum('final_premium');

        return response()->json([
            'data' => [
                'total_requests' => $total,
                'issued' => $issued,
                'pending_confirmation' => $pending,
                'needs_reconciliation' => $needsRecon,
                'failed' => $failed,
                'submitted' => $submitted,
                'received' => $received,
                'validated' => $validated,
                'total_premium_issued' => (string) number_format((float) $totalPremiumIssued, 2, '.', ''),
            ],
            'meta' => [
                'calculated_at' => now()->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * POST /api/operations/reconcile
     * Triggers reconciliation pass over pending or uncertain requests.
     */
    public function reconcile(Request $request): JsonResponse
    {
        $limit = min(500, max(1, (int) $request->input('limit', 100)));

        $result = $this->reconciliationService->reconcile($limit);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => "Reconciliation completed. Examined {$result['examined']} records, reconciled {$result['reconciled']}.",
        ], 200);
    }
}
