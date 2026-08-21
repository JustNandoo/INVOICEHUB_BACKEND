<?php

namespace App\Jobs\Ai;

use App\Models\AiRun;
use App\Models\AiUsageRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ai_runs keeps the model output for forensics, which includes customer names. It is
 * pruned on the same retention policy as notifications so the data does not pile up
 * indefinitely. Usage records are aggregated cost figures without personal data, so they
 * are kept longer for reporting.
 */
class PruneAiRuns implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue(config('ai.queue', 'ai'));
    }

    public function handle(): void
    {
        $cutoff = now()->subDays((int) config('ai.retention_days', 180));

        AiRun::query()->where('created_at', '<', $cutoff)->delete();
        AiUsageRecord::query()->where('created_at', '<', $cutoff->copy()->subYear())->delete();
    }
}
