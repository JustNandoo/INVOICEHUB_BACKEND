<?php

namespace App\Jobs\Ai;

use App\Models\User;
use App\Services\Ai\Features\FinancialInsightService;
use App\Services\Subscription\EntitlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Fan-out for the daily refresh. Users are filtered here rather than inside the per-user
 * job so a plan without insights never occupies a queue slot at all.
 */
class DispatchFinancialInsightRefresh implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 86400;

    public function __construct()
    {
        $this->onQueue(config('ai.queue', 'ai'));
    }

    public function handle(EntitlementService $entitlements, FinancialInsightService $insights): void
    {
        if (! config('ai.enabled')) {
            return;
        }

        User::query()
            ->whereNotNull('email_verified_at')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($entitlements, $insights): void {
                foreach ($users as $user) {
                    try {
                        if (! $entitlements->has($user, 'ai.insights') || ! $insights->isDue($user)) {
                            continue;
                        }
                    } catch (Throwable) {
                        continue;
                    }

                    GenerateFinancialInsights::dispatch($user->id);
                }
            });
    }

    public function uniqueId(): string
    {
        return now()->toDateString();
    }
}
