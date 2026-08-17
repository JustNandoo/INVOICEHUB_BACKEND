<?php

namespace App\Listeners\Notification;

use App\Enums\Notification\BusinessNotificationType;
use App\Enums\Notification\NotificationIcon;
use App\Enums\Notification\NotificationTone;
use App\Events\Report\RevenueTargetReached;
use App\Models\MonthlyRevenueTarget;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class StoreRevenueTargetReachedNotification implements ShouldQueueAfterCommit
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(RevenueTargetReached $event): void
    {
        $target = MonthlyRevenueTarget::query()->with('owner')->find($event->targetId);

        if (! $target || ! $target->reached_at) {
            return;
        }

        $this->notifications->createOnce(
            $target->owner,
            BusinessNotificationType::RevenueTargetReached,
            "revenue-target-reached:{$target->id}:{$target->amount}",
            'Target Tercapai!',
            'Selamat! Pendapatan bulan ini telah melampaui target yang Anda tetapkan.',
            NotificationTone::Pink,
            NotificationIcon::Celebration,
            '/dashboard',
            ['targetId' => $target->id, 'targetAmount' => $target->amount, 'currentRevenue' => $event->currentRevenue],
        );
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
