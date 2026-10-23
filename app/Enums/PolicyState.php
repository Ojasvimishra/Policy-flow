<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Formal lifecycle states for a PolicyFlow policy request.
 */
enum PolicyState: string
{
    case RECEIVED = 'RECEIVED';
    case VALIDATED = 'VALIDATED';
    case SUBMITTED = 'SUBMITTED';
    case PENDING_CONFIRMATION = 'PENDING_CONFIRMATION';
    case ISSUED = 'ISSUED';
    case FAILED = 'FAILED';
    case NEEDS_RECONCILIATION = 'NEEDS_RECONCILIATION';

    /**
     * Determines if the state is terminal (cannot transition to any other state).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::ISSUED => true,
            default => false,
        };
    }

    /**
     * Returns true if this state represents an ambiguous or in-flight provider outcome.
     */
    public function isPending(): bool
    {
        return match ($this) {
            self::SUBMITTED, self::PENDING_CONFIRMATION, self::NEEDS_RECONCILIATION => true,
            default => false,
        };
    }

    /**
     * Human-readable label for operational reporting.
     */
    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Received',
            self::VALIDATED => 'Validated',
            self::SUBMITTED => 'Submitted to Insurer',
            self::PENDING_CONFIRMATION => 'Pending Provider Confirmation',
            self::ISSUED => 'Policy Issued',
            self::FAILED => 'Failed',
            self::NEEDS_RECONCILIATION => 'Needs Reconciliation',
        };
    }

    /**
     * All valid state string values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
