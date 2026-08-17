<?php

namespace App\Jobs\Report;

use App\Events\Report\WeeklyFinancialReportReady;
use App\Models\User;
use App\Services\Report\WeeklyFinancialReportService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateUserWeeklyFinancialReport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 86400;

    public function __construct(
        public readonly int $userId,
        public readonly string $periodStart,
        public readonly string $periodEnd,
    ) {
        $this->onQueue('reports');
    }

    public function handle(WeeklyFinancialReportService $reports): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $report = $reports->generate(
            $user,
            CarbonImmutable::parse($this->periodStart),
            CarbonImmutable::parse($this->periodEnd),
        );
        WeeklyFinancialReportReady::dispatch($report->id);
    }

    public function uniqueId(): string
    {
        return "{$this->userId}:{$this->periodStart}";
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
