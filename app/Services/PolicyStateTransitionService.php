<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PolicyState;
use App\Exceptions\InvalidStateTransitionException;

/**
 * Validates and enforces policy lifecycle state transitions.
 * Guarantees that terminal states (ISSUED) cannot be regressed or overwritten.
 */
class PolicyStateTransitionService
{
    /**
     * Authoritative State Transition Matrix.
     * Maps each state to its permitted target states.
     *
     * @var array<string, array<string>>
     */
    protected const TRANSITION_MATRIX = [
        PolicyState::RECEIVED->value => [
            PolicyState::VALIDATED->value,
            PolicyState::FAILED->value,
        ],
        PolicyState::VALIDATED->value => [
            PolicyState::SUBMITTED->value,
            PolicyState::FAILED->value,
        ],
        PolicyState::SUBMITTED->value => [
            PolicyState::ISSUED->value,
            PolicyState::PENDING_CONFIRMATION->value,
            PolicyState::NEEDS_RECONCILIATION->value,
            PolicyState::FAILED->value,
        ],
        PolicyState::PENDING_CONFIRMATION->value => [
            PolicyState::ISSUED->value,
            PolicyState::NEEDS_RECONCILIATION->value,
            PolicyState::FAILED->value,
        ],
        PolicyState::NEEDS_RECONCILIATION->value => [
            PolicyState::ISSUED->value,
            PolicyState::SUBMITTED->value,
            PolicyState::FAILED->value,
        ],
        PolicyState::FAILED->value => [
            PolicyState::SUBMITTED->value, // Permitted only for safe retries
        ],
        PolicyState::ISSUED->value => [
            // STRICT TERMINAL STATE: Zero transitions allowed out of ISSUED.
        ],
    ];

    /**
     * Determines whether a transition from $from to $to is valid.
     */
    public function canTransition(
        PolicyState|string $from,
        PolicyState|string $to,
        bool $allowSameState = false
    ): bool {
        $fromState = $this->resolveState($from);
        $toState = $this->resolveState($to);

        if ($fromState === null || $toState === null) {
            return false;
        }

        if ($fromState === $toState) {
            return $allowSameState;
        }

        $allowedTargets = self::TRANSITION_MATRIX[$fromState->value] ?? [];

        return in_array($toState->value, $allowedTargets, true);
    }

    /**
     * Validates that the requested transition is permitted, throwing an exception if not.
     *
     * @throws InvalidStateTransitionException
     */
    public function validateTransition(
        PolicyState|string $from,
        PolicyState|string $to,
        ?int $policyId = null,
        bool $allowSameState = false
    ): void {
        $fromState = $this->resolveState($from);
        $toState = $this->resolveState($to);

        $fromStr = $from instanceof PolicyState ? $from->value : (string) $from;
        $toStr = $to instanceof PolicyState ? $to->value : (string) $to;

        if ($fromState === null) {
            throw new InvalidStateTransitionException(
                $fromStr,
                $toStr,
                $policyId,
                "Origin state '{$fromStr}' is not a recognized PolicyState"
            );
        }

        if ($toState === null) {
            throw new InvalidStateTransitionException(
                $fromStr,
                $toStr,
                $policyId,
                "Target state '{$toStr}' is not a recognized PolicyState"
            );
        }

        if ($fromState === $toState) {
            if ($allowSameState) {
                return;
            }
            throw new InvalidStateTransitionException(
                $fromStr,
                $toStr,
                $policyId,
                "Duplicate transition to identical state '{$toStr}' is not permitted without explicit idempotent bypass"
            );
        }

        if ($fromState->isTerminal()) {
            throw new InvalidStateTransitionException(
                $fromStr,
                $toStr,
                $policyId,
                "State '{$fromStr}' is a terminal state and cannot transition to any other state"
            );
        }

        $allowedTargets = self::TRANSITION_MATRIX[$fromState->value] ?? [];

        if (! in_array($toState->value, $allowedTargets, true)) {
            $allowedList = empty($allowedTargets) ? 'none (terminal)' : implode(', ', $allowedTargets);
            throw new InvalidStateTransitionException(
                $fromStr,
                $toStr,
                $policyId,
                "Transition not allowed in matrix. Permitted transitions from '{$fromStr}': [{$allowedList}]"
            );
        }
    }

    /**
     * Returns the array of allowed target states from a given origin state.
     *
     * @return array<string>
     */
    public function getAllowedTransitions(PolicyState|string $from): array
    {
        $state = $this->resolveState($from);
        if ($state === null) {
            return [];
        }

        return self::TRANSITION_MATRIX[$state->value] ?? [];
    }

    /**
     * Safely resolves a string or PolicyState into a PolicyState instance.
     */
    public function resolveState(PolicyState|string $state): ?PolicyState
    {
        if ($state instanceof PolicyState) {
            return $state;
        }

        return PolicyState::tryFrom(trim($state));
    }
}
