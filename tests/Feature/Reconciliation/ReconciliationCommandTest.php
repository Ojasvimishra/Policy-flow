<?php

declare(strict_types=1);

namespace Tests\Feature\Reconciliation;

use App\Enums\PolicyState;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Reconciliation successfully matches and issues a timed-out policy.
     */
    public function test_reconciliation_resolves_pending_policy_to_issued(): void
    {
        // Internal policy stuck in PENDING_CONFIRMATION due to timeout
        $policy = PolicyRequest::create([
            'client_request_id' => 'AGT-REC-001',
            'agent_id' => 'AGENT-01',
            'customer_name' => 'Charlie Brown',
            'date_of_birth' => '1990-01-01',
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

        // Provider side policy created prior to network timeout
        MockInsurerPolicy::create([
            'client_request_id' => 'AGT-REC-001',
            'agent_id' => 'AGENT-01',
            'policy_number' => 'POL-REC-1001',
            'provider_reference' => 'PRV-REC-REF-999',
            'status' => 'ISSUED',
            'sum_insured' => '100000.00',
            'premium_charged' => '75.00',
        ]);

        $this->artisan('policyflow:reconcile', ['--limit' => 10])
            ->assertExitCode(0);

        $policy->refresh();
        $this->assertEquals(PolicyState::ISSUED, $policy->status);
        $this->assertEquals('POL-REC-1001', $policy->policy_number);
        $this->assertEquals('PRV-REC-REF-999', $policy->provider_reference);

        $this->assertDatabaseHas('policy_state_history', [
            'policy_request_id' => $policy->id,
            'from_state' => 'PENDING_CONFIRMATION',
            'to_state' => 'ISSUED',
            'trigger' => 'RECONCILIATION_MATCHED_ISSUED',
        ]);

        // Second run must be idempotent and perform no mutations
        $historyCount = PolicyStateHistory::count();
        $this->artisan('policyflow:reconcile', ['--limit' => 10])
            ->assertExitCode(0);

        $this->assertEquals($historyCount, PolicyStateHistory::count());
    }

    /**
     * Test 2: Reconciliation definitively marks unissued policies as FAILED for safe retry.
     */
    public function test_reconciliation_marks_missing_provider_record_as_failed(): void
    {
        // Policy stuck in PENDING_CONFIRMATION, but provider never received it
        $policy = PolicyRequest::create([
            'client_request_id' => 'AGT-REC-002',
            'agent_id' => 'AGENT-01',
            'customer_name' => 'David Miller',
            'date_of_birth' => '1985-03-12',
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

        // Mock insurer has NO record of AGT-REC-002

        $this->artisan('policyflow:reconcile', ['--limit' => 10])
            ->assertExitCode(0);

        $policy->refresh();
        $this->assertEquals(PolicyState::FAILED, $policy->status);
        $this->assertNull($policy->policy_number);

        $this->assertDatabaseHas('policy_state_history', [
            'policy_request_id' => $policy->id,
            'from_state' => 'PENDING_CONFIRMATION',
            'to_state' => 'FAILED',
            'trigger' => 'RECONCILIATION_NOT_FOUND',
        ]);
    }
}
