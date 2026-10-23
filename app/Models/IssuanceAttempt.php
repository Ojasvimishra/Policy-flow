<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks an individual issuance attempt dispatched to an insurer.
 *
 * @property int $id
 * @property int $policy_request_id
 * @property int $attempt_number
 * @property string $provider
 * @property string|null $scenario_requested
 * @property array $request_payload
 * @property int|null $response_status_code
 * @property array|null $response_payload
 * @property string|null $error_detail
 * @property string $status
 * @property Carbon $attempted_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PolicyRequest $policyRequest
 */
class IssuanceAttempt extends Model
{
    protected $table = 'issuance_attempts';

    protected $fillable = [
        'policy_request_id',
        'attempt_number',
        'provider',
        'scenario_requested',
        'request_payload',
        'response_status_code',
        'response_payload',
        'error_detail',
        'status',
        'attempted_at',
        'completed_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'request_payload' => 'array',
        'response_status_code' => 'integer',
        'response_payload' => 'array',
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Parent policy request.
     */
    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class, 'policy_request_id');
    }
}
