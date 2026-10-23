<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PolicyState;
use DomainException;
use Throwable;

class InvalidStateTransitionException extends DomainException
{
    protected string $fromState;
    protected string $toState;
    protected ?int $policyId;

    public function __construct(
        string|PolicyState $fromState,
        string|PolicyState $toState,
        ?int $policyId = null,
        string $additionalReason = '',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        $from = $fromState instanceof PolicyState ? $fromState->value : $fromState;
        $to = $toState instanceof PolicyState ? $toState->value : $toState;

        $this->fromState = $from;
        $this->toState = $to;
        $this->policyId = $policyId;

        $message = "Invalid state transition from '{$from}' to '{$to}'";
        if ($policyId !== null) {
            $message .= " for PolicyRequest #{$policyId}";
        }
        if ($additionalReason !== '') {
            $message .= ": {$additionalReason}";
        }

        parent::__construct($message, $code, $previous);
    }

    public function getFromState(): string
    {
        return $this->fromState;
    }

    public function getToState(): string
    {
        return $this->toState;
    }

    public function getPolicyId(): ?int
    {
        return $this->policyId;
    }

    /**
     * Context for structured logging and API error envelope.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'error_code' => 'INVALID_STATE_TRANSITION',
            'policy_id' => $this->policyId,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
        ];
    }
}
