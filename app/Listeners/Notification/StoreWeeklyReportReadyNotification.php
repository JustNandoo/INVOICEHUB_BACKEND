<?php

namespace App\Listeners\Notification;

use App\Enums\Notification\BusinessNotificationType;
use App\Enums\Notification\NotificationIcon;
use App\Enums\Notification\NotificationTone;
use App\Events\Report\WeeklyFinancialReportReady;
use App\Models\WeeklyFinancialReport;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class StoreWeeklyReportReadyNotification implements ShouldQueueAfterCommit
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(WeeklyFinancialReportReady $event): void
    {
        $report = WeeklyFinancialReport::query()->with('owner')->find($event->reportId);

        if (! $report) {
            return;
        }

        $this->notifications->createOnce(
            $report->owner,
            BusinessNotificationType::WeeklyReportReady,
            "weekly-report:{$report->owner->id}:{$report->period_start->format('Y-m-d')}",
            'Laporan Mingguan Siap',
            'Ringkasan arus kas minggu lalu sudah bisa diunduh.',
            NotificationTone::Muted,
            NotificationIcon::File,
            "/dashboard?weeklyReport={$report->id}",
            ['reportId' => $report->id],
        );
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
