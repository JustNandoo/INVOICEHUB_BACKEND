<?php

namespace App\Services\Ai;

use App\Enums\Ai\AiFeature;
use App\Exceptions\Ai\AiDisabledException;
use App\Exceptions\Ai\AiQuotaExceededException;
use App\Models\AiUsageRecord;
use App\Models\User;
use App\Services\Subscription\EntitlementService;

class AiCreditService
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    /**
     * Runs before any provider call. Order matters: the cheapest checks first, and the
     * global cost guard last so one runaway tenant cannot spend the whole budget.
     *
     * @throws AiQuotaExceededException|AiDisabledException
     */
    public function assertCanSpend(User $user, AiFeature $feature): void
    {
        $this->entitlements->require($user, $feature->entitlement());

        $plan = $this->entitlements->subscription($user)->plan;
        $cost = $feature->credits();

        $dailyLimit = $plan->limit('aiCreditsDaily');
        if ($dailyLimit !== null) {
            $usedToday = $this->creditsUsed($user, since: now()->startOfDay());
            if ($usedToday + $cost > $dailyLimit) {
                throw new AiQuotaExceededException('daily', $usedToday, $dailyLimit, now()->addDay()->startOfDay()->toIso8601String());
            }
        }

        $monthlyLimit = $plan->limit('aiCreditsMonthly');
        if ($monthlyLimit !== null) {
            $usedThisMonth = $this->creditsUsed($user, since: now()->startOfMonth());
            if ($usedThisMonth + $cost > $monthlyLimit) {
                throw new AiQuotaExceededException('monthly', $usedThisMonth, $monthlyLimit, now()->addMonth()->startOfMonth()->toIso8601String());
            }
        }

        $this->assertGlobalBudgetAvailable();
    }

    /**
     * Last-resort guard: a bug or abuse cannot cost more than the configured daily cap.
     */
    public function assertGlobalBudgetAvailable(): void
    {
        $limit = (int) config('ai.daily_cost_limit_idr', 0);

        if ($limit <= 0) {
            return;
        }

        $spent = (int) AiUsageRecord::query()->where('created_at', '>=', now()->startOfDay())->sum('estimated_cost');

        if ($spent >= $limit) {
            throw new AiDisabledException('daily_cost_limit_reached');
        }
    }

    public function record(User $user, AiFeature $feature, string $model, int $inputTokens, int $outputTokens, int $estimatedCost, ?int $aiRunId): AiUsageRecord
    {
        return AiUsageRecord::query()->create([
            'user_id' => $user->id,
            'ai_run_id' => $aiRunId,
            'feature' => $feature->value,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost' => $estimatedCost,
            'credits_used' => $feature->credits(),
        ]);
    }

    /**
     * @return array<string, array{used: int, limit: int|null, remaining: int|null, unlimited: bool}>
     */
    public function usage(User $user): array
    {
        $plan = $this->entitlements->subscription($user)->plan;

        return [
            'daily' => $this->usageItem(
                $this->creditsUsed($user, now()->startOfDay()),
                $plan->limit('aiCreditsDaily'),
            ),
            'monthly' => $this->usageItem(
                $this->creditsUsed($user, now()->startOfMonth()),
                $plan->limit('aiCreditsMonthly'),
            ),
        ];
    }

    private function creditsUsed(User $user, \DateTimeInterface $since): int
    {
        return (int) AiUsageRecord::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->sum('credits_used');
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
