<?php

use App\Jobs\Ai\DispatchFinancialInsightRefresh;
use App\Jobs\Ai\PruneAiRuns;
use App\Jobs\Anomaly\DispatchAnomalyDetection;
use App\Jobs\Marketplace\DispatchMarketplaceSync;
use App\Jobs\Notification\CreateUpcomingInvoiceDueNotifications;
use App\Jobs\Notification\PruneReadNotifications;
use App\Jobs\Report\DispatchWeeklyFinancialReports;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::job(new CreateUpcomingInvoiceDueNotifications)
    ->dailyAt('08:00')
    ->timezone(config('notifications.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new DispatchWeeklyFinancialReports)
    ->mondays()
    ->at('06:00')
    ->timezone(config('notifications.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new PruneReadNotifications)
    ->dailyAt('02:00')
    ->timezone(config('notifications.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new DispatchMarketplaceSync)
    ->hourly()
    ->timezone(config('notifications.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new DispatchAnomalyDetection)
    ->dailyAt('06:30')
    ->timezone(config('notifications.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new DispatchFinancialInsightRefresh)
    ->dailyAt('07:00')
    ->timezone(config('notifications.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new PruneAiRuns)
    ->dailyAt('02:30')
    ->timezone(config('notifications.timezone'))
    ->withoutOverlapping()
    ->onOneServer();
