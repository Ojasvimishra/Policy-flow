<?php

declare(strict_types=1);

namespace Tests\Feature\Issuance;

use App\Enums\PolicyState;
use App\Models\IssuanceAttempt;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Services\MockInsurer\MockInsurerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockInsurerScenariosTest extends TestCase
{
    use RefreshDatabase;

    protected function createValidPolicyRequest(string $clientRequestId = 'AGT-REQ-101'): PolicyRequest
    {
        return PolicyRequest::create([
            'client_request_id' => $clientRequestId,
            'agent_id' => 'AGENT-01',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1995-05-15',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'plan_code' => 'PLUS',
            'sum_insured' => '300000.00',
            'travel_days' => 5,
            'base_daily_rate' => '20.00',
            'age_multiplier' => '1.00',
            'destination_multiplier' => '1.80',
            'coverage_multiplier' => '1.25',
            'calculated_premium' => '225.00',
            'final_premium' => '225.00',
            'status' => PolicyState::RECEIVED,
        ]);
    }

    /**
     * Scenario 1: SUCCESS
     * Standard issuance flow transitions to ISSUED, creates 1 provider policy, assigns policy_number.
     * Repeated submit returns identical policy without second call.
     */
    public function test_scenario_success_issues_policy_and_is_idempotent(): void
    {
        $policy = $this->createValidPolicyRequest('AGT-SCN-SUCCESS');

        $response = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => MockInsurerService::SCENARIO_SUCCESS,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', PolicyState::ISSUED->value)
            ->assertJsonPath('idempotent_replay', false);

        $policy->refresh();
        $this->assertEquals(PolicyState::ISSUED, $policy->status);
        $this->assertNotNull($policy->policy_number);
        $this->assertNotNull($policy->provider_reference);
        $this->assertEquals(1, $policy->attempt_count);

        // Ensure exactly 1 MockInsurerPolicy created
        $this->assertDatabaseCount('mock_insurer_policies', 1);
        $this->assertDatabaseHas('mock_insurer_policies', [
            'client_request_id' => 'AGT-SCN-SUCCESS',
            'status' => 'ISSUED',
        ]);

        // Ensure IssuanceAttempt record
        $this->assertDatabaseHas('issuance_attempts', [
            'policy_request_id' => $policy->id,
            'attempt_number' => 1,
            'status' => 'SUCCESS',
            'response_status_code' => 200,
        ]);

        // Repeated submission returns identical canonical policy without duplicating
        $repeatResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit");
        $repeatResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', PolicyState::ISSUED->value)
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('policy_number', $policy->policy_number);

        $this->assertDatabaseCount('mock_insurer_policies', 1);
        $this->assertEquals(1, $policy->fresh()->attempt_count);
    }

    /**
     * Scenario 2: TEMPORARY_FAILURE
     * Insurer returns 503; request transitions to FAILED; no provider policy created; safe retry succeeds.
     */
    public function test_scenario_temporary_failure_allows_safe_retry(): void
    {
        $policy = $this->createValidPolicyRequest('AGT-SCN-TEMP-FAIL');

        $response = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => MockInsurerService::SCENARIO_TEMPORARY_FAILURE,
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', PolicyState::FAILED->value)
            ->assertJsonPath('retriable', true);

        $policy->refresh();
        $this->assertEquals(PolicyState::FAILED, $policy->status);
        $this->assertNull($policy->policy_number);
        $this->assertEquals(1, $policy->attempt_count);

        // Crucial: Provider policy must NOT be created
        $this->assertDatabaseCount('mock_insurer_policies', 0);

        // Attempt logged as TEMPORARY_FAILURE
        $this->assertDatabaseHas('issuance_attempts', [
            'policy_request_id' => $policy->id,
            'attempt_number' => 1,
            'status' => 'TEMPORARY_FAILURE',
            'response_status_code' => 503,
        ]);

        // Permitted Safe Retry (Scenario SUCCESS on retry)
        $retryResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => MockInsurerService::SCENARIO_SUCCESS,
        ]);

        $retryResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', PolicyState::ISSUED->value);

        $policy->refresh();
        $this->assertEquals(PolicyState::ISSUED, $policy->status);
        $this->assertEquals(2, $policy->attempt_count);
        $this->assertDatabaseCount('mock_insurer_policies', 1);
    }

    /**
     * Scenario 3: TIMEOUT_AFTER_ISSUE
     * Provider policy IS created, caller times out, policy enters PENDING_CONFIRMATION, blind reissue rejected.
     */
    public function test_scenario_timeout_after_issue_prevents_blind_reissue(): void
    {
        $policy = $this->createValidPolicyRequest('AGT-SCN-TIMEOUT');

        $response = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => MockInsurerService::SCENARIO_TIMEOUT_AFTER_ISSUE,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', PolicyState::PENDING_CONFIRMATION->value)
            ->assertJsonPath('requires_reconciliation', true);

        $policy->refresh();
        $this->assertEquals(PolicyState::PENDING_CONFIRMATION, $policy->status);

        // Invariant: Provider policy WAS created before timeout
        $this->assertDatabaseCount('mock_insurer_policies', 1);
        $mockPolicy = MockInsurerPolicy::where('client_request_id', 'AGT-SCN-TIMEOUT')->first();
        $this->assertNotNull($mockPolicy);

        // Attempt logged as TIMEOUT
        $this->assertDatabaseHas('issuance_attempts', [
            'policy_request_id' => $policy->id,
            'status' => 'TIMEOUT',
            'response_status_code' => 504,
        ]);

        // Immediate blind reissue must be prevented!
        $reissueResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit");
        $reissueResponse->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', PolicyState::PENDING_CONFIRMATION->value)
            ->assertJsonPath('requires_reconciliation', true);

        // Provider policy count must remain exactly 1!
        $this->assertDatabaseCount('mock_insurer_policies', 1);
    }

    /**
     * Scenario 4: DELAYED_CALLBACK
     * Provider accepts as PENDING, awaits webhook callback.
     */
    public function test_scenario_delayed_callback_sets_pending_confirmation(): void
    {
        $policy = $this->createValidPolicyRequest('AGT-SCN-DELAYED');

        $response = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => MockInsurerService::SCENARIO_DELAYED_CALLBACK,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', PolicyState::PENDING_CONFIRMATION->value);

        $policy->refresh();
        $this->assertEquals(PolicyState::PENDING_CONFIRMATION, $policy->status);
        $this->assertNotNull($policy->provider_reference);

        $this->assertDatabaseHas('mock_insurer_policies', [
            'client_request_id' => 'AGT-SCN-DELAYED',
            'status' => 'PENDING',
        ]);
    }
}
