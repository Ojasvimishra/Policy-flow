<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Isolated persistence for the simulated provider's issued policies.
 * Decoupled from internal policy_requests ledger.
 *
 * @property int $id
 * @property string $client_request_id
 * @property string $agent_id
 * @property string $policy_number
 * @property string $provider_reference
 * @property string $status
 * @property string $sum_insured
 * @property string $premium_charged
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MockInsurerPolicy extends Model
{
    protected $table = 'mock_insurer_policies';

    protected $fillable = [
        'client_request_id',
        'agent_id',
        'policy_number',
        'provider_reference',
        'status',
        'sum_insured',
        'premium_charged',
        'metadata',
    ];

    protected $casts = [
        'sum_insured' => 'decimal:2',
        'premium_charged' => 'decimal:2',
        'metadata' => 'array',
    ];
}
