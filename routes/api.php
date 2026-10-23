<?php

use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\MockInsurerCallbackController;
use App\Http\Controllers\Api\MockInsurerController;
use App\Http\Controllers\Api\OperationsController;
use App\Http\Controllers\Api\PolicyRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthCheckController::class, 'check']);

// Operational Dashboard Metrics & Reconciliation
Route::get('/dashboard/metrics', [OperationsController::class, 'metrics']);
Route::post('/operations/reconcile', [OperationsController::class, 'reconcile']);

// Policy Intake, Retrieval, and Issuance Submission Endpoints
Route::post('/policy-requests', [PolicyRequestController::class, 'store']);
Route::get('/policy-requests', [PolicyRequestController::class, 'index']);
Route::get('/policy-requests/{id}', [PolicyRequestController::class, 'show'])->whereNumber('id');
Route::post('/policy-requests/{id}/submit', [PolicyRequestController::class, 'submit'])->whereNumber('id');

// Deterministic Mock Insurer Integration Endpoints
Route::post('/mock-insurer/issue', [MockInsurerController::class, 'issue']);
Route::get('/mock-insurer/status/{client_request_id}', [MockInsurerController::class, 'status']);
Route::post('/mock-insurer/callback', [MockInsurerCallbackController::class, 'handleCallback']);
