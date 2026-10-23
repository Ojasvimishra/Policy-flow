<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Provide a comprehensive, non-sensitive health check of all critical infrastructure services.
     */
    public function check(): JsonResponse
    {
        $status = 'healthy';
        $services = [];
        $statusCode = 200;

        // 1. Database Connectivity Check
        try {
            $dbStartTime = microtime(true);
            DB::connection()->getPdo();
            $dbDurationMs = round((microtime(true) - $dbStartTime) * 1000, 2);

            $services['database'] = [
                'status' => 'up',
                'driver' => DB::connection()->getDriverName(),
                'latency_ms' => $dbDurationMs,
            ];
        } catch (Throwable $e) {
            $status = 'unhealthy';
            $statusCode = 503;
            $services['database'] = [
                'status' => 'down',
                'error' => 'Database connection failed',
            ];
        }

        // 2. Redis Connectivity Check
        try {
            $redisStartTime = microtime(true);
            $redisPing = Redis::ping();
            $redisDurationMs = round((microtime(true) - $redisStartTime) * 1000, 2);

            $services['redis'] = [
                'status' => ($redisPing === true || $redisPing === '+PONG' || $redisPing === 'PONG') ? 'up' : 'degraded',
                'latency_ms' => $redisDurationMs,
            ];
        } catch (Throwable $e) {
            // If Redis fails, mark degraded or unhealthy
            $status = 'unhealthy';
            $statusCode = 503;
            $services['redis'] = [
                'status' => 'down',
                'error' => 'Redis connection failed',
            ];
        }

        // 3. Storage Writability Check
        $storageWritable = is_writable(storage_path('framework/cache'));
        $services['storage'] = [
            'status' => $storageWritable ? 'up' : 'degraded',
            'writable' => $storageWritable,
        ];

        if (! $storageWritable) {
            $status = 'unhealthy';
            $statusCode = 503;
        }

        return response()->json([
            'status' => $status,
            'app' => config('app.name', 'PolicyFlow'),
            'environment' => config('app.env', 'production'),
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
        ], $statusCode);
    }
}
