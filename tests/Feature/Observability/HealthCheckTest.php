<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_check_endpoint_returns_json_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'app',
                'environment',
                'timestamp',
                'services' => [
                    'database' => ['status'],
                    'redis' => ['status'],
                    'storage' => ['status', 'writable'],
                ],
            ]);

        $this->assertEquals('healthy', $response->json('status'));
        $this->assertEquals('up', $response->json('services.database.status'));
        $this->assertTrue($response->json('services.storage.writable'));
    }

    public function test_laravel_up_endpoint_returns_ok(): void
    {
        $response = $this->get('/up');
        $response->assertOk();
    }
}
