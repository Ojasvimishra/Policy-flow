<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Enums\PolicyState;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Services\MockInsurer\MockInsurerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Empty database dashboard metrics contains strictly zero fake data.
     */
    public function test_dashboard_metrics_empty_database_returns_accurate_zeroes(): void
    {
        $response = $this->getJson('/api/dashboard/metrics');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_requests', 0)
            ->assertJsonPath('data.issued', 0)
            ->assertJsonPath('data.pending_confirmation', 0)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.total_premium_issued', '0.00');
    }

    /**
     * Test 2: Full End-to-End lifecycle via real HTTP endpoints.
     * Intake -> Retrieve -> Submit -> Issued -> Idempotent Re-submit -> Metrics reflection.
     */
    public function test_end_to_end_policy_lifecycle_and_metrics(): void
    {
        // Step 1: Intake
        $intakePayload = [
            'client_request_id' => 'E2E-REQ-999',
            'agent_id' => 'AGENT-E2E',
            'customer_name' => 'Aditi Sharma',
            'date_of_birth' => '1998-06-15',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'plan_code' => 'BASIC',
            'sum_insured' => '100000.00',
        ];

        $intakeResponse = $this->postJson('/api/policy-requests', $intakePayload);
        $intakeResponse->assertStatus(201)
            ->assertJsonPath('data.status', PolicyState::RECEIVED->value)
            ->assertJsonPath('data.final_premium', '60.00');

        $policyId = $intakeResponse->json('data.id');

        // Step 2: Retrieve policy
        $getResponse = $this->getJson("/api/policy-requests/{$policyId}");
        $getResponse->assertStatus(200)
            ->assertJsonPath('data.id', $policyId)
            ->assertJsonPath('data.status', PolicyState::RECEIVED->value);

        // Step 3: Submit to insurer
        $submitResponse = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => MockInsurerService::SCENARIO_SUCCESS,
        ]);

        $submitResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', PolicyState::ISSUED->value);

        $policyNumber = $submitResponse->json('policy_number');
        $this->assertNotNull($policyNumber);

        // Step 4: Re-submit for idempotent replay
        $resubmitResponse = $this->postJson("/api/policy-requests/{$policyId}/submit");
        $resubmitResponse->assertStatus(200)
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('policy_number', $policyNumber);

        // Step 5: Dashboard reflects exactly 1 issued policy
        $metricsResponse = $this->getJson('/api/dashboard/metrics');
        $metricsResponse->assertStatus(200)
            ->assertJsonPath('data.total_requests', 1)
            ->assertJsonPath('data.issued', 1)
            ->assertJsonPath('data.pending_confirmation', 0)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.total_premium_issued', '60.00');
    }

    /**
     * Test 3: Operations HTTP reconciliation endpoint.
     */
    public function test_operations_reconcile_endpoint_resolves_pending_policy(): void
    {
        $policy = PolicyRequest::create([
            'client_request_id' => 'OPS-REC-001',
            'agent_id' => 'AGENT-OPS',
            'customer_name' => 'Kunal Kapoor',
            'date_of_birth' => '1991-04-10',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'plan_code' => 'BASIC',
            'sum_insured' => '100000.00',
            'travel_days' => 5,
            'base_daily_rate' => '15.00',
            'age_multiplier' => '1.00',
            'destination_multiplier' => '1.00',
            'coverage_multiplier' => '1.00',
            'final_premium' => '75.00',
            'status' => PolicyState::PENDING_CONFIRMATION,
        ]);

        MockInsurerPolicy::create([
            'client_request_id' => 'OPS-REC-001',
            'agent_id' => 'AGENT-OPS',
            'policy_number' => 'POL-OPS-777',
            'provider_reference' => 'REF-OPS-777',
            'status' => 'ISSUED',
            'sum_insured' => '100000.00',
            'premium_charged' => '75.00',
        ]);

        $response = $this->postJson('/api/operations/reconcile', ['limit' => 10]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reconciled', 1);

        $policy->refresh();
        $this->assertEquals(PolicyState::ISSUED, $policy->status);
        $this->assertEquals('POL-OPS-777', $policy->policy_number);
    }
}
