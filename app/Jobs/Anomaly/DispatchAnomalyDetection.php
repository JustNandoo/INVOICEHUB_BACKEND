<?php

namespace App\Jobs\Anomaly;

use App\Models\User;
use App\Services\Subscription\EntitlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchAnomalyDetection implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 86400;

    public function __construct()
    {
        $this->onQueue('reports');
    }

    public function handle(EntitlementService $entitlements): void
    {
        if (! config('anomaly.enabled')) {
            return;
        }

        User::query()
            ->whereNotNull('email_verified_at')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($entitlements): void {
                foreach ($users as $user) {
                    try {
                        if (! $entitlements->has($user, 'anomaly.detection')) {
                            continue;
                        }
                    } catch (Throwable) {
                        continue;
                    }

                    DetectFinancialAnomalies::dispatch($user->id);
                }
            });
    }

    public function uniqueId(): string
    {
        return now()->toDateString();
    }
}
