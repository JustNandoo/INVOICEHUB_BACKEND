<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'price' => $this->price,
            'currency' => 'IDR',
            'billingInterval' => $this->billing_interval,
            'description' => $this->description,
            'features' => $this->features,
            'benefits' => config("subscriptions.plans.{$this->code}.benefits", []),
            'limits' => $this->limits,
            'isRecommended' => $this->is_recommended,
        ];
    }
}
