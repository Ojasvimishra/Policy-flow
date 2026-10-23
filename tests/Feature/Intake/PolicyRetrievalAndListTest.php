<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyRetrievalAndListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Retrieve single existing policy request by ID returns HTTP 200.
     */
    public function test_show_existing_policy_returns_200(): void
    {
        $policy = PolicyRequest::create([
            'agent_id' => 'AGENT-17',
            'client_request_id' => 'AGT-REQ-100',
            'customer_name' => 'Alice Smith',
            'date_of_birth' => '1995-05-15',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'travel_days' => 5,
            'plan_code' => 'BASIC',
            'sum_insured' => '100000.00',
            'base_daily_rate' => '12.00',
            'age_multiplier' => '1.00',
            'destination_multiplier' => '1.00',
            'coverage_multiplier' => '1.00',
            'final_premium' => '60.00',
            'status' => PolicyState::RECEIVED,
        ]);

        $response = $this->getJson("/api/policy-requests/{$policy->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $policy->id)
            ->assertJsonPath('data.customer_name', 'Alice Smith')
            ->assertJsonPath('data.final_premium', '60.00')
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonStructure(['data', 'meta' => ['retrieved_at']]);
    }

    /**
     * Test 2: Retrieve non-existent policy request returns HTTP 404.
     */
    public function test_show_non_existent_policy_returns_404(): void
    {
        $response = $this->getJson('/api/policy-requests/99999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'POLICY_NOT_FOUND')
            ->assertJsonStructure(['error' => ['code', 'message']]);
    }

    /**
     * Test 3: List endpoint returns paginated results with deterministic ordering.
     */
    public function test_index_returns_paginated_deterministic_results(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            PolicyRequest::create([
                'agent_id' => 'AGENT-17',
                'client_request_id' => "AGT-PAGE-{$i}",
                'customer_name' => "Customer {$i}",
                'date_of_birth' => '1990-01-01',
                'destination' => 'DOMESTIC',
                'trip_start' => '2026-12-01',
                'trip_end' => '2026-12-03',
                'travel_days' => 3,
                'plan_code' => 'BASIC',
                'sum_insured' => '100000.00',
                'base_daily_rate' => '12.00',
                'age_multiplier' => '1.00',
                'destination_multiplier' => '1.00',
                'coverage_multiplier' => '1.00',
                'final_premium' => '36.00',
                'status' => PolicyState::RECEIVED,
            ]);
        }

        $response = $this->getJson('/api/policy-requests?per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3);

        $this->assertCount(2, $response->json('data'));
    }

    /**
     * Test 4: List endpoint filters by status, agent_id, destination, and plan_code.
     */
    public function test_index_applies_query_filters_accurately(): void
    {
        PolicyRequest::create([
            'agent_id' => 'AGENT-DOMESTIC',
            'client_request_id' => 'REQ-DOM-1',
            'customer_name' => 'Domestic Customer',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-02',
            'travel_days' => 2,
            'plan_code' => 'BASIC',
            'sum_insured' => '100000.00',
            'base_daily_rate' => '12.00',
            'age_multiplier' => '1.00',
            'destination_multiplier' => '1.00',
            'coverage_multiplier' => '1.00',
            'final_premium' => '24.00',
            'status' => PolicyState::RECEIVED,
        ]);

        PolicyRequest::create([
            'agent_id' => 'AGENT-INTL',
            'client_request_id' => 'REQ-INTL-1',
            'customer_name' => 'International Customer',
            'date_of_birth' => '1990-01-01',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-02',
            'travel_days' => 2,
            'plan_code' => 'PLUS',
            'sum_insured' => '300000.00',
            'base_daily_rate' => '20.00',
            'age_multiplier' => '1.00',
            'destination_multiplier' => '1.80',
            'coverage_multiplier' => '1.25',
            'final_premium' => '90.00',
            'status' => PolicyState::VALIDATED,
        ]);

        // Filter by destination: INTERNATIONAL
        $resIntl = $this->getJson('/api/policy-requests?destination=INTERNATIONAL');
        $resIntl->assertStatus(200);
        $this->assertCount(1, $resIntl->json('data'));
        $this->assertSame('AGENT-INTL', $resIntl->json('data.0.agent_id'));

        // Filter by status: VALIDATED
        $resVal = $this->getJson('/api/policy-requests?status=VALIDATED');
        $resVal->assertStatus(200);
        $this->assertCount(1, $resVal->json('data'));
        $this->assertSame('AGENT-INTL', $resVal->json('data.0.agent_id'));
    }

    /**
     * Test 5: Invalid filter parameter returns HTTP 422.
     */
    public function test_invalid_filter_parameter_returns_422(): void
    {
        $response = $this->getJson('/api/policy-requests?status=NON_EXISTENT_STATUS');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_FILTER_PARAMETERS');
    }
}
