<?php

namespace Tests\Feature\Ai;

use App\Jobs\Ai\PruneAiRuns;
use App\Models\AiRun;
use App\Models\AiUsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneAiRunsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_runs_older_than_the_retention_window(): void
    {
        config()->set('ai.retention_days', 180);
        $user = User::factory()->create();

        $old = $this->makeRun($user, now()->subDays(200));
        $recent = $this->makeRun($user, now()->subDays(10));

        (new PruneAiRuns)->handle();

        $this->assertDatabaseMissing('ai_runs', ['id' => $old->id]);
        $this->assertDatabaseHas('ai_runs', ['id' => $recent->id]);
    }

    public function test_usage_records_are_kept_longer_for_cost_reporting(): void
    {
        config()->set('ai.retention_days', 180);
        $user = User::factory()->create();

        $keep = AiUsageRecord::query()->create([
            'user_id' => $user->id, 'feature' => 'financial_insight', 'model' => 'm', 'credits_used' => 1,
        ]);
        $keep->forceFill(['created_at' => now()->subDays(200)])->save();

        (new PruneAiRuns)->handle();

        $this->assertDatabaseHas('ai_usage_records', ['id' => $keep->id]);
    }

    private function makeRun(User $user, \DateTimeInterface $createdAt): AiRun
    {
        $run = AiRun::query()->create([
            'user_id' => $user->id, 'feature' => 'reconciliation_analysis', 'provider' => 'gemini',
            'model' => 'm', 'prompt_version' => 'v1', 'status' => 'succeeded', 'input_hash' => str_repeat('a', 64),
        ]);
        $run->forceFill(['created_at' => $createdAt])->save();

        return $run;
    }
}
