<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only historical record of policy state transitions.
 *
 * @property int $id
 * @property int $policy_request_id
 * @property string $from_state
 * @property string $to_state
 * @property string $trigger
 * @property string|null $reason
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property-read PolicyRequest $policyRequest
 */
class PolicyStateHistory extends Model
{
    protected $table = 'policy_state_history';

    // Immutable append-only ledger; no updated_at column
    public $timestamps = false;

    protected $fillable = [
        'policy_request_id',
        'from_state',
        'to_state',
        'trigger',
        'reason',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Parent policy request.
     */
    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class, 'policy_request_id');
    }
}
