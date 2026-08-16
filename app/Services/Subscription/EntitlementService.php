<?php

namespace App\Services\Subscription;

use App\Exceptions\SubscriptionAccessDeniedException;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserSubscription;

class EntitlementService
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function subscription(User $user): UserSubscription
    {
        return $this->subscriptions->current($user);
    }

    public function has(User $user, string $feature): bool
    {
        return $this->subscription($user)->plan->hasFeature($feature);
    }

    public function require(User $user, string $feature): void
    {
        $subscription = $this->subscription($user);
        if (! $subscription->plan->hasFeature($feature)) {
            throw new SubscriptionAccessDeniedException($feature, $subscription->plan->code);
        }
    }

    public function assertCanCreateInvoice(User $user): void
    {
        $subscription = $this->subscription($user);
        $limit = $subscription->plan->limit('monthlyInvoices');
        if ($limit === null) {
            return;
        }

        $used = Invoice::query()->where('user_id', $user->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        if ($used >= $limit) {
            throw new SubscriptionAccessDeniedException(
                'invoice.create', $subscription->plan->code, "monthlyInvoices:{$limit}",
            );
        }
    }

    /** @return array<string, array<string, int|bool|null>> */
    public function usage(User $user): array
    {
        $plan = $this->subscription($user)->plan;
        $invoiceLimit = $plan->limit('monthlyInvoices');
        $bankLimit = $plan->limit('bankAccounts');
        $invoiceUsed = Invoice::query()->where('user_id', $user->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $bankUsed = $user->bankAccounts()->count();

        return [
            'monthlyInvoices' => $this->usageItem($invoiceUsed, $invoiceLimit),
            'bankAccounts' => $this->usageItem($bankUsed, $bankLimit),
            'stores' => $this->usageItem(1, $plan->limit('stores')),
        ];
    }

    /** @return array{used: int, limit: int|null, remaining: int|null, unlimited: bool} */
    private function usageItem(int $used, ?int $limit): array
    {
        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'unlimited' => $limit === null,
        ];
    }
}
