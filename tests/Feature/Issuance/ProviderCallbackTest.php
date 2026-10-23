<?php

declare(strict_types=1);

namespace Tests\Feature\Issuance;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Models\ProviderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function createPendingPolicy(string $clientRequestId = 'AGT-CB-001'): PolicyRequest
    {
        return PolicyRequest::create([
            'client_request_id' => $clientRequestId,
            'agent_id' => 'AGENT-01',
            'customer_name' => 'Alice Walker',
            'date_of_birth' => '1992-08-20',
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
            'calculated_premium' => '75.00',
            'final_premium' => '75.00',
            'status' => PolicyState::PENDING_CONFIRMATION,
            'provider_reference' => 'PRV-REF-CB-1234',
        ]);
    }

    /**
     * Test 1: Callback successfully reconciles PENDING_CONFIRMATION policy to ISSUED.
     */
    public function test_valid_callback_updates_policy_to_issued(): void
    {
        $policy = $this->createPendingPolicy('AGT-CB-001');

        $payload = [
            'provider' => 'mock_insurer',
            'event_id' => 'EVT-WEBHOOK-999',
            'event_type' => 'POLICY_ISSUED',
            'client_request_id' => 'AGT-CB-001',
            'provider_reference' => 'PRV-REF-CB-1234',
            'policy_number' => 'POL-CB-999',
            'payload' => [
                'status' => 'ISSUED',
            ],
        ];

        $response = $this->postJson('/api/mock-insurer/callback', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'PROCESSED_ISSUED')
            ->assertJsonPath('policy_number', 'POL-CB-999')
            ->assertJsonPath('idempotent_replay', false);

        $policy->refresh();
        $this->assertEquals(PolicyState::ISSUED, $policy->status);
        $this->assertEquals('POL-CB-999', $policy->policy_number);

        $this->assertDatabaseHas('provider_events', [
            'provider' => 'mock_insurer',
            'event_id' => 'EVT-WEBHOOK-999',
            'processed' => true,
        ]);

        $this->assertDatabaseHas('policy_state_history', [
            'policy_request_id' => $policy->id,
            'from_state' => 'PENDING_CONFIRMATION',
            'to_state' => 'ISSUED',
            'trigger' => 'PROVIDER_WEBHOOK_CALLBACK',
        ]);
    }

    /**
     * Test 2: Duplicate callback with identical event_id is safely deduplicated without side effects.
     */
    public function test_duplicate_callback_is_deduplicated_safely(): void
    {
        $policy = $this->createPendingPolicy('AGT-CB-002');

        $payload = [
            'provider' => 'mock_insurer',
            'event_id' => 'EVT-DUPLICATE-111',
            'event_type' => 'POLICY_ISSUED',
            'client_request_id' => 'AGT-CB-002',
            'provider_reference' => 'PRV-REF-CB-1234',
            'policy_number' => 'POL-CB-111',
        ];

        // First delivery
        $res1 = $this->postJson('/api/mock-insurer/callback', $payload);
        $res1->assertStatus(200)->assertJsonPath('status', 'PROCESSED_ISSUED');

        $historyCount = PolicyStateHistory::where('policy_request_id', $policy->id)->count();

        // Second delivery (duplicate replay)
        $res2 = $this->postJson('/api/mock-insurer/callback', $payload);
        $res2->assertStatus(200)
            ->assertJsonPath('status', 'DUPLICATE_ACKNOWLEDGED')
            ->assertJsonPath('idempotent_replay', true);

        // Invariants: exactly 1 event record, no extra state history rows
        $this->assertDatabaseCount('provider_events', 1);
        $this->assertEquals($historyCount, PolicyStateHistory::where('policy_request_id', $policy->id)->count());
    }

    /**
     * Test 3: Never downgrade an already ISSUED policy even if an out-of-order failure callback arrives.
     */
    public function test_failure_callback_cannot_downgrade_already_issued_policy(): void
    {
        $policy = $this->createPendingPolicy('AGT-CB-003');
        $policy->status = PolicyState::ISSUED;
        $policy->policy_number = 'POL-SECURE-888';
        $policy->save();

        $payload = [
            'provider' => 'mock_insurer',
            'event_id' => 'EVT-LATE-FAILURE-999',
            'event_type' => 'POLICY_FAILED',
            'client_request_id' => 'AGT-CB-003',
            'payload' => [
                'status' => 'FAILED',
            ],
        ];

        $response = $this->postJson('/api/mock-insurer/callback', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'SUCCESS_UNCHANGED')
            ->assertJsonPath('current_status', 'ISSUED');

        $policy->refresh();
        $this->assertEquals(PolicyState::ISSUED, $policy->status);
        $this->assertEquals('POL-SECURE-888', $policy->policy_number);
    }
}
