<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\ProviderCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

/**
 * Ingests external provider webhook callbacks.
 */
class MockInsurerCallbackController extends Controller
{
    public function __construct(
        protected ProviderCallbackService $callbackService
    ) {}

    /**
     * POST /api/mock-insurer/callback
     * Receive and process webhook event.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (empty($payload['event_id'])) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CALLBACK_PAYLOAD',
                    'message' => 'The event_id field is required.',
                ],
            ], 422);
        }

        try {
            $result = $this->callbackService->processCallback($payload);

            return response()->json($result, 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CALLBACK_ARGUMENT',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => [
                    'code' => 'CALLBACK_PROCESSING_FAILED',
                    'message' => 'Unable to process provider callback at this time.',
                ],
            ], 500);
        }
    }
}
