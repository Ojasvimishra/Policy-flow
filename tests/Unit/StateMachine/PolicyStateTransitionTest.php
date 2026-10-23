<?php

declare(strict_types=1);

namespace Tests\Unit\StateMachine;

use App\Enums\PolicyState;
use App\Exceptions\InvalidStateTransitionException;
use App\Services\PolicyStateTransitionService;
use PHPUnit\Framework\TestCase;

class PolicyStateTransitionTest extends TestCase
{
    protected PolicyStateTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PolicyStateTransitionService;
    }

    /**
     * Case 1: RECEIVED -> VALIDATED is permitted.
     */
    public function test_received_to_validated_is_permitted(): void
    {
        $this->assertTrue(
            $this->service->canTransition(PolicyState::RECEIVED, PolicyState::VALIDATED)
        );

        // Should not throw
        $this->service->validateTransition(PolicyState::RECEIVED, PolicyState::VALIDATED, 101);
    }

    /**
     * Case 2: VALIDATED -> SUBMITTED is permitted.
     */
    public function test_validated_to_submitted_is_permitted(): void
    {
        $this->assertTrue(
            $this->service->canTransition(PolicyState::VALIDATED, PolicyState::SUBMITTED)
        );

        $this->service->validateTransition(PolicyState::VALIDATED, PolicyState::SUBMITTED, 102);
    }

    /**
     * Case 3: SUBMITTED -> ISSUED is permitted when the chosen matrix allows confirmed success.
     */
    public function test_submitted_to_issued_is_permitted(): void
    {
        $this->assertTrue(
            $this->service->canTransition(PolicyState::SUBMITTED, PolicyState::ISSUED)
        );

        $this->service->validateTransition(PolicyState::SUBMITTED, PolicyState::ISSUED, 103);
    }

    /**
     * Case 4: SUBMITTED -> PENDING_CONFIRMATION is permitted on timeout or ambiguous provider response.
     */
    public function test_submitted_to_pending_confirmation_is_permitted(): void
    {
        $this->assertTrue(
            $this->service->canTransition(PolicyState::SUBMITTED, PolicyState::PENDING_CONFIRMATION)
        );

        $this->service->validateTransition(PolicyState::SUBMITTED, PolicyState::PENDING_CONFIRMATION, 104);
    }

    /**
     * Case 5: PENDING_CONFIRMATION -> ISSUED is permitted when issuance is confirmed.
     */
    public function test_pending_confirmation_to_issued_is_permitted(): void
    {
        $this->assertTrue(
            $this->service->canTransition(PolicyState::PENDING_CONFIRMATION, PolicyState::ISSUED)
        );

        $this->service->validateTransition(PolicyState::PENDING_CONFIRMATION, PolicyState::ISSUED, 105);
    }

    /**
     * Case 6: NEEDS_RECONCILIATION -> ISSUED is permitted when issuance is confirmed.
     */
    public function test_needs_reconciliation_to_issued_is_permitted(): void
    {
        $this->assertTrue(
            $this->service->canTransition(PolicyState::NEEDS_RECONCILIATION, PolicyState::ISSUED)
        );

        $this->service->validateTransition(PolicyState::NEEDS_RECONCILIATION, PolicyState::ISSUED, 106);
    }

    /**
     * Case 7: ISSUED -> SUBMITTED is rejected (Absolute Invariant: ISSUED is terminal).
     */
    public function test_issued_to_submitted_is_rejected(): void
    {
        $this->assertFalse(
            $this->service->canTransition(PolicyState::ISSUED, PolicyState::SUBMITTED)
        );

        $this->expectException(InvalidStateTransitionException::class);
        $this->service->validateTransition(PolicyState::ISSUED, PolicyState::SUBMITTED, 107);
    }

    /**
     * Case 8: ISSUED -> FAILED is rejected (Cannot overwrite confirmed policy with failure).
     */
    public function test_issued_to_failed_is_rejected(): void
    {
        $this->assertFalse(
            $this->service->canTransition(PolicyState::ISSUED, PolicyState::FAILED)
        );

        $this->expectException(InvalidStateTransitionException::class);
        $this->service->validateTransition(PolicyState::ISSUED, PolicyState::FAILED, 108);
    }

    /**
     * Case 9: Unknown or invalid states are rejected.
     */
    public function test_unknown_or_invalid_states_are_rejected(): void
    {
        $this->assertFalse(
            $this->service->canTransition('INVALID_ORIGIN_STATE', PolicyState::VALIDATED->value)
        );

        $this->expectException(InvalidStateTransitionException::class);
        $this->service->validateTransition('NON_EXISTENT_STATE', PolicyState::VALIDATED->value, 109);
    }

    /**
     * Case 10: A transition not present in the matrix is rejected.
     */
    public function test_transition_not_present_in_matrix_is_rejected(): void
    {
        // RECEIVED cannot jump directly to ISSUED without validation and submission
        $this->assertFalse(
            $this->service->canTransition(PolicyState::RECEIVED, PolicyState::ISSUED)
        );

        $this->expectException(InvalidStateTransitionException::class);
        $this->service->validateTransition(PolicyState::RECEIVED, PolicyState::ISSUED, 110);
    }

    /**
     * Case 11: Duplicate state changes are handled according to documented policy.
     */
    public function test_duplicate_state_changes_are_handled_by_policy(): void
    {
        // By default, duplicate transition without bypass flag is rejected
        $this->assertFalse(
            $this->service->canTransition(PolicyState::VALIDATED, PolicyState::VALIDATED)
        );

        // When explicit idempotent same-state bypass is enabled, it returns true
        $this->assertTrue(
            $this->service->canTransition(PolicyState::VALIDATED, PolicyState::VALIDATED, allowSameState: true)
        );

        // Throw verification
        $this->expectException(InvalidStateTransitionException::class);
        $this->service->validateTransition(PolicyState::VALIDATED, PolicyState::VALIDATED, 111);
    }

    /**
     * Case 12: The exception contains useful, non-sensitive diagnostic information.
     */
    public function test_exception_contains_useful_non_sensitive_diagnostics(): void
    {
        try {
            $this->service->validateTransition(PolicyState::ISSUED, PolicyState::FAILED, 999);
            $this->fail('Expected InvalidStateTransitionException was not thrown.');
        } catch (InvalidStateTransitionException $e) {
            $this->assertSame('ISSUED', $e->getFromState());
            $this->assertSame('FAILED', $e->getToState());
            $this->assertSame(999, $e->getPolicyId());

            $context = $e->context();
            $this->assertSame('INVALID_STATE_TRANSITION', $context['error_code']);
            $this->assertSame(999, $context['policy_id']);
            $this->assertSame('ISSUED', $context['from_state']);
            $this->assertSame('FAILED', $context['to_state']);

            // Must NOT expose secrets, credentials, or customer PII
            $this->assertStringNotContainsString('password', strtolower($e->getMessage()));
            $this->assertStringNotContainsString('secret', strtolower($e->getMessage()));
        }
    }
}
