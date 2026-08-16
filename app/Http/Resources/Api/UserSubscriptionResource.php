<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'source' => $this->source,
            'plan' => (new SubscriptionPlanResource($this->plan))->resolve($request),
            'startsAt' => $this->starts_at?->toIso8601String(),
            'currentPeriodStartsAt' => $this->current_period_starts_at?->toIso8601String(),
            'currentPeriodEndsAt' => $this->current_period_ends_at?->toIso8601String(),
            'endsAt' => $this->ends_at?->toIso8601String(),
        ];
    }
}
