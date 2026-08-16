<?php

namespace App\Services\Subscription;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Collection;

class PlanCatalogService
{
    /** @return Collection<int, SubscriptionPlan> */
    public function all(): Collection
    {
        $this->ensureAvailable();

        return SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    public function find(string $code): SubscriptionPlan
    {
        $this->ensureAvailable();

        return SubscriptionPlan::query()->where('code', $code)->where('is_active', true)->firstOrFail();
    }

    public function synchronize(): void
    {
        foreach ((array) config('subscriptions.plans') as $code => $plan) {
            SubscriptionPlan::query()->updateOrCreate(['code' => $code], [
                'name' => $plan['name'],
                'price' => $plan['price'],
                'billing_interval' => $plan['billing_interval'],
                'description' => $plan['description'],
                'features' => $plan['features'],
                'limits' => $plan['limits'],
                'sort_order' => $plan['sort_order'],
                'is_recommended' => $plan['is_recommended'],
                'is_active' => true,
            ]);
        }
    }

    private function ensureAvailable(): void
    {
        $expected = count((array) config('subscriptions.plans'));
        if (SubscriptionPlan::query()->where('is_active', true)->count() < $expected) {
            $this->synchronize();
        }
    }
}
