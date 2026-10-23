<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIntakeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Valid intake request returns HTTP 201 Created and persists pricing snapshot.
     */
    public function test_valid_request_returns_201_created_with_pricing_snapshot(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.client_request_id', 'AGT-2026-0001')
            ->assertJsonPath('data.agent_id', 'AGENT-17')
            ->assertJsonPath('data.travel_days', 5)
            ->assertJsonPath('data.base_daily_rate', '20.00')
            ->assertJsonPath('data.age_multiplier', '1.00')
            ->assertJsonPath('data.destination_multiplier', '1.80')
            ->assertJsonPath('data.coverage_multiplier', '1.25')
            ->assertJsonPath('data.final_premium', '225.00')
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->assertDatabaseHas('policy_requests', [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'final_premium' => '225.00',
            'status' => 'RECEIVED',
        ]);

        $this->assertDatabaseHas('policy_state_history', [
            'to_state' => 'RECEIVED',
            'trigger' => 'POLICY_INTAKE',
        ]);
    }

    /**
     * Test 2: Missing required fields are rejected with HTTP 422.
     */
    public function test_missing_required_fields_return_422(): void
    {
        $response = $this->postJson('/api/policy-requests', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'fields' => [
                        'client_request_id',
                        'agent_id',
                        'customer_name',
                        'date_of_birth',
                        'destination',
                        'trip_start',
                        'trip_end',
                        'plan_code',
                        'sum_insured',
                    ],
                ],
            ]);
    }

    /**
     * Test 3: Unsupported destination is rejected with HTTP 422.
     */
    public function test_unsupported_destination_returns_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0002',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'EUROPE', // Invalid
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);
        $response->assertStatus(422);
    }

    /**
     * Test 4: Unsupported plan code is rejected with HTTP 422.
     */
    public function test_unsupported_plan_code_returns_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0003',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'GOLD', // Invalid
            'sum_insured' => 100000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);
        $response->assertStatus(422);
    }

    /**
     * Test 5: Unsupported sum insured is rejected with HTTP 422.
     */
    public function test_unsupported_sum_insured_returns_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0004',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'BASIC',
            'sum_insured' => 200000, // Invalid (only 100k, 300k, 500k allowed)
        ];

        $response = $this->postJson('/api/policy-requests', $payload);
        $response->assertStatus(422);
    }

    /**
     * Test 6: trip_end earlier than trip_start is rejected with HTTP 422.
     */
    public function test_trip_end_before_trip_start_returns_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0005',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-09', // Earlier
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);
        $response->assertStatus(422);
    }

    /**
     * Test 7: Client-supplied premium, status, or policy number cannot override server logic.
     */
    public function test_client_cannot_override_server_managed_fields(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0006',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Attacker',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-10',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            // Malicious overrides:
            'final_premium' => '1.00',
            'status' => 'ISSUED',
            'policy_number' => 'FAKE-POL-999',
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.final_premium', '12.00') // True calculated price
            ->assertJsonPath('data.status', 'RECEIVED')     // Initial state, not ISSUED
            ->assertJsonPath('data.policy_number', null);   // Null initially
    }

    /**
     * Test 8: Repeated intake with identical (agent_id, client_request_id) returns HTTP 200.
     */
    public function test_duplicate_intake_returns_200_ok_as_idempotent_replay(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0007',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        // Initial submission
        $res1 = $this->postJson('/api/policy-requests', $payload);
        $res1->assertStatus(201)
            ->assertJsonPath('meta.idempotent_replay', false);
        $policyId = $res1->json('data.id');

        // Repeated submission
        $res2 = $this->postJson('/api/policy-requests', $payload);
        $res2->assertStatus(200)
            ->assertJsonPath('data.id', $policyId)
            ->assertJsonPath('data.final_premium', '225.00')
            ->assertJsonPath('meta.idempotent_replay', true);

        // Database row count must be exactly 1
        $count = PolicyRequest::where('agent_id', 'AGENT-17')
            ->where('client_request_id', 'AGT-2026-0007')
            ->count();

        $this->assertSame(1, $count);
    }

    /**
     * Test 9: Different agent can use same client_request_id.
     */
    public function test_different_agents_can_share_client_request_id(): void
    {
        $payload1 = [
            'client_request_id' => 'SHARED-REQ-01',
            'agent_id' => 'AGENT-A',
            'customer_name' => 'Customer A',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-10',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        $payload2 = array_merge($payload1, ['agent_id' => 'AGENT-B']);

        $res1 = $this->postJson('/api/policy-requests', $payload1);
        $res1->assertStatus(201);

        $res2 = $this->postJson('/api/policy-requests', $payload2);
        $res2->assertStatus(201);

        $this->assertNotSame($res1->json('data.id'), $res2->json('data.id'));
    }
}
