<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PolicyRequest
 */
class PolicyRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_request_id' => $this->client_request_id,
            'agent_id' => $this->agent_id,
            'customer_name' => $this->customer_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'destination' => $this->destination,
            'trip_start' => $this->trip_start?->format('Y-m-d'),
            'trip_end' => $this->trip_end?->format('Y-m-d'),
            'travel_days' => $this->travel_days,
            'plan_code' => $this->plan_code,
            'sum_insured' => $this->sum_insured,
            'base_daily_rate' => $this->base_daily_rate,
            'age_multiplier' => $this->age_multiplier,
            'destination_multiplier' => $this->destination_multiplier,
            'coverage_multiplier' => $this->coverageMultiplier ?? $this->coverage_multiplier,
            'final_premium' => $this->final_premium,
            'status' => $this->status instanceof PolicyState ? $this->status->value : (string) $this->status,
            'policy_number' => $this->policy_number,
            'provider_reference' => $this->provider_reference,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'attempts' => $this->whenLoaded('attempts'),
            'state_history' => $this->whenLoaded('stateHistory'),
            'provider_events' => $this->whenLoaded('providerEvents'),
        ];
    }
}
