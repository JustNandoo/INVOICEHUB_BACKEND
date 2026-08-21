<?php

namespace App\Jobs\Anomaly;

use App\Enums\Notification\BusinessNotificationType;
use App\Enums\Notification\NotificationIcon;
use App\Enums\Notification\NotificationTone;
use App\Exceptions\SubscriptionAccessDeniedException;
use App\Models\User;
use App\Services\Anomaly\AnomalyDetectionService;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Detection is deterministic, so it runs on the general reports queue rather than the AI
 * one; it costs nothing and must not be delayed behind slow provider calls.
 */
class DetectFinancialAnomalies implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $userId)
    {
        $this->onQueue('reports');
    }

    public function handle(AnomalyDetectionService $detection, NotificationService $notifications): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        try {
            $result = $detection->scan($user);
        } catch (SubscriptionAccessDeniedException) {
            return;
        }

        if ($result['detected'] === 0) {
            return;
        }

        $notifications->createOnce(
            $user,
            BusinessNotificationType::AnomalyDetected,
            'anomaly.detected:'.now()->toDateString(),
            'Potensi kebocoran terdeteksi',
            sprintf(
                '%d temuan baru dengan potensi nilai Rp %s.',
                $result['detected'],
                number_format($result['amountAtRisk'], 0, ',', '.'),
            ),
            NotificationTone::Pink,
            NotificationIcon::Alert,
            '/dashboard',
            ['detected' => $result['detected'], 'amountAtRisk' => $result['amountAtRisk']],
        );
    }

    public function uniqueId(): string
    {
        return $this->userId.':'.now()->toDateString();
    }
}
