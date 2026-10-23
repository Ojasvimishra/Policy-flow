<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PolicyState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Canonical Policy Request entity.
 *
 * @property int $id
 * @property string $agent_id
 * @property string $client_request_id
 * @property string $customer_name
 * @property Carbon $date_of_birth
 * @property string $destination
 * @property Carbon $trip_start
 * @property Carbon $trip_end
 * @property int $travel_days
 * @property string $plan_code
 * @property string $sum_insured
 * @property string $base_daily_rate
 * @property string $age_multiplier
 * @property string $destination_multiplier
 * @property string $coverage_multiplier
 * @property string $final_premium
 * @property PolicyState $status
 * @property string|null $policy_number
 * @property string|null $provider_reference
 * @property int $attempt_count
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, IssuanceAttempt> $attempts
 * @property-read Collection<int, PolicyStateHistory> $stateHistory
 * @property-read Collection<int, ProviderEvent> $providerEvents
 */
class PolicyRequest extends Model
{
    protected $table = 'policy_requests';

    /**
     * Mass assignable attributes.
     * Note: status, policy_number, provider_reference, and pricing snapshots
     * are strictly protected and managed via domain services.
     */
    protected $fillable = [
        'agent_id',
        'client_request_id',
        'customer_name',
        'date_of_birth',
        'destination',
        'trip_start',
        'trip_end',
        'travel_days',
        'plan_code',
        'sum_insured',
        'base_daily_rate',
        'age_multiplier',
        'destination_multiplier',
        'coverage_multiplier',
        'final_premium',
        'status',
        'policy_number',
        'provider_reference',
        'attempt_count',
        'metadata',
    ];

    /**
     * Attribute type casting.
     */
    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'trip_start' => 'date:Y-m-d',
        'trip_end' => 'date:Y-m-d',
        'travel_days' => 'integer',
        'sum_insured' => 'decimal:2',
        'base_daily_rate' => 'decimal:2',
        'age_multiplier' => 'decimal:2',
        'destination_multiplier' => 'decimal:2',
        'coverage_multiplier' => 'decimal:2',
        'final_premium' => 'decimal:2',
        'status' => PolicyState::class,
        'attempt_count' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Issuance attempts associated with this request.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class, 'policy_request_id')->orderBy('attempt_number');
    }

    /**
     * Complete chronological state transition history.
     */
    public function stateHistory(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class, 'policy_request_id')->orderBy('created_at');
    }

    /**
     * Provider callback events delivered for this policy.
     */
    public function providerEvents(): HasMany
    {
        return $this->hasMany(ProviderEvent::class, 'policy_request_id')->orderBy('created_at');
    }

    /**
     * Check if policy has reached confirmed terminal ISSUED state.
     */
    public function isIssued(): bool
    {
        return $this->status === PolicyState::ISSUED;
    }

    /**
     * Check if request has an immutable pricing snapshot populated.
     */
    public function hasPricingSnapshot(): bool
    {
        return $this->final_premium !== null && $this->final_premium !== '';
    }

    /**
     * Set the immutable pricing snapshot.
     */
    public function setPricingSnapshot(
        string|float $baseDailyRate,
        string|float $ageMultiplier,
        string|float $destinationMultiplier,
        string|float $coverageMultiplier,
        string|float $finalPremium
    ): void {
        $this->base_daily_rate = (string) $baseDailyRate;
        $this->age_multiplier = (string) $ageMultiplier;
        $this->destination_multiplier = (string) $destinationMultiplier;
        $this->coverage_multiplier = (string) $coverageMultiplier;
        $this->final_premium = (string) $finalPremium;
    }
}
