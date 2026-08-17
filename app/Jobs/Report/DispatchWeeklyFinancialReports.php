<?php

namespace App\Jobs\Report;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchWeeklyFinancialReports implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 86400;

    public function __construct()
    {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $now = CarbonImmutable::now(config('notifications.timezone'));
        $start = $now->subWeek()->startOfWeek();
        $end = $start->endOfWeek();

        User::query()->whereNotNull('email_verified_at')->orderBy('id')->chunkById(200, function ($users) use ($start, $end): void {
            foreach ($users as $user) {
                GenerateUserWeeklyFinancialReport::dispatch(
                    $user->id,
                    $start->toDateString(),
                    $end->toDateString(),
                );
            }
        });
    }

    public function uniqueId(): string
    {
        return CarbonImmutable::now(config('notifications.timezone'))->subWeek()->startOfWeek()->toDateString();
    }
}
