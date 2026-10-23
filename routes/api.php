<?php

use App\Http\Controllers\Api\PolicyRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Policy Intake & Retrieval REST Endpoints
Route::post('/policy-requests', [PolicyRequestController::class, 'store']);
Route::get('/policy-requests', [PolicyRequestController::class, 'index']);
Route::get('/policy-requests/{id}', [PolicyRequestController::class, 'show'])->whereNumber('id');
