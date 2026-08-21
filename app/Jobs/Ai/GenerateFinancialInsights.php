<?php

namespace App\Jobs\Ai;

use App\Enums\Notification\BusinessNotificationType;
use App\Enums\Notification\NotificationIcon;
use App\Enums\Notification\NotificationTone;
use App\Exceptions\Ai\AiDisabledException;
use App\Exceptions\Ai\AiProviderException;
use App\Exceptions\Ai\AiQuotaExceededException;
use App\Exceptions\Ai\AiValidationException;
use App\Exceptions\SubscriptionAccessDeniedException;
use App\Models\User;
use App\Services\Ai\Features\FinancialInsightService;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateFinancialInsights implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $userId)
    {
        $this->onQueue(config('ai.queue', 'ai'));
    }

    public function handle(FinancialInsightService $insights, NotificationService $notifications): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        try {
            $generated = $insights->generate($user);
        } catch (AiDisabledException|AiQuotaExceededException|SubscriptionAccessDeniedException $exception) {
            // Expected, non-actionable conditions: the dashboard simply keeps the old batch.
            return;
        } catch (AiProviderException|AiValidationException $exception) {
            Log::warning('Financial insight generation failed', [
                'userId' => $this->userId, 'reason' => $exception->getMessage(),
            ]);

            return;
        }

        if ($generated->isEmpty()) {
            return;
        }

        $notifications->createOnce(
            $user,
            BusinessNotificationType::AiInsightsReady,
            'ai.insights:'.now()->toDateString(),
            'Insight keuangan terbaru siap',
            $generated->count().' insight baru dari analisis keuangan Anda.',
            NotificationTone::Blue,
            NotificationIcon::File,
            '/dashboard',
            ['insightCount' => $generated->count()],
        );
    }

    public function uniqueId(): string
    {
        return $this->userId.':'.now()->toDateString();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }
}
