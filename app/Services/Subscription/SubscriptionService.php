<?php

namespace App\Services\Subscription;

use App\Models\User;
use App\Models\UserSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(private readonly PlanCatalogService $plans) {}

    public function current(User $user): UserSubscription
    {
        UserSubscription::query()->where('user_id', $user->id)
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_ends_at')->where('current_period_ends_at', '<=', now())
            ->update(['status' => UserSubscription::STATUS_EXPIRED, 'ends_at' => now()]);

        $current = UserSubscription::query()->with('plan')
            ->where('user_id', $user->id)->where('status', UserSubscription::STATUS_ACTIVE)
            ->where(function ($query): void {
                $query->whereNull('current_period_ends_at')->orWhere('current_period_ends_at', '>', now());
            })->latest('starts_at')->first();

        return $current ?? $this->assignDefault($user);
    }

    public function assignDefault(User $user): UserSubscription
    {
        return DB::transaction(function () use ($user): UserSubscription {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = UserSubscription::query()->with('plan')
                ->where('user_id', $user->id)->where('status', UserSubscription::STATUS_ACTIVE)
                ->where(function ($query): void {
                    $query->whereNull('current_period_ends_at')->orWhere('current_period_ends_at', '>', now());
                })
                ->latest('starts_at')->first();
            if ($existing) {
                return $existing;
            }

            return UserSubscription::query()->create([
                'user_id' => $user->id,
                'subscription_plan_id' => $this->plans->find('starter')->id,
                'status' => UserSubscription::STATUS_ACTIVE,
                'source' => 'default',
                'starts_at' => now(),
                'current_period_starts_at' => now(),
            ])->load('plan');
        }, 3);
    }

    /**
     * Internal activation point for a future verified payment webhook or an admin action.
     * This method is intentionally not exposed as a public purchase endpoint.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function activatePlan(
        User $user,
        string $planCode,
        string $source = 'payment_provider',
        ?CarbonImmutable $periodEndsAt = null,
        array $metadata = [],
    ): UserSubscription {
        return DB::transaction(function () use ($user, $planCode, $source, $periodEndsAt, $metadata): UserSubscription {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $plan = $this->plans->find($planCode);
            $now = now()->toImmutable();

            UserSubscription::query()->where('user_id', $user->id)
                ->where('status', UserSubscription::STATUS_ACTIVE)
                ->update(['status' => UserSubscription::STATUS_EXPIRED, 'ends_at' => $now]);

            return UserSubscription::query()->create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => UserSubscription::STATUS_ACTIVE,
                'source' => $source,
                'starts_at' => $now,
                'current_period_starts_at' => $now,
                'current_period_ends_at' => $plan->price === 0 ? null : ($periodEndsAt ?? $now->addMonth()),
                'metadata' => $metadata ?: null,
            ])->load('plan');
        }, 3);
    }
}
