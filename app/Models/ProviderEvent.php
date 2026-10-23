<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persists provider webhook callbacks and enforces event deduplication.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $event_type
 * @property int|null $policy_request_id
 * @property string|null $provider_reference
 * @property array $payload
 * @property bool $processed
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PolicyRequest|null $policyRequest
 */
class ProviderEvent extends Model
{
    protected $table = 'provider_events';

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'policy_request_id',
        'provider_reference',
        'payload',
        'processed',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    /**
     * Associated policy request, if identified.
     */
    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class, 'policy_request_id');
    }
}
