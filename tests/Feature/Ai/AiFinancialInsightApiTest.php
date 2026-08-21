<?php

namespace Tests\Feature\Ai;

use App\Enums\Ai\AiRunStatus;
use App\Exceptions\Ai\AiValidationException;
use App\Jobs\Ai\DispatchFinancialInsightRefresh;
use App\Jobs\Ai\GenerateFinancialInsights;
use App\Models\AiInsight;
use App\Models\AiRun;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Ai\Features\FinancialInsightService;
use App\Services\Notification\NotificationService;
use App\Services\Subscription\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiFinancialInsightApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.enabled', true);
        config()->set('ai.providers.gemini.key', 'test-key');
    }

    public function test_it_requires_verified_authentication(): void
    {
        $this->getJson('/api/v1/ai/insights')->assertUnauthorized();

        $user = User::factory()->unverified()->create();
        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/v1/ai/insights')->assertForbidden();
    }

    public function test_reading_the_dashboard_never_calls_the_ai_provider(): void
    {
        Http::fake();
        [$user] = $this->scenario('basic');
        $this->storedInsight($user);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/v1/ai/insights')
            ->assertOk()
            ->assertJsonCount(1, 'data.insights')
            ->assertJsonPath('data.insights.0.title', 'Piutang jatuh tempo menumpuk');

        // Ten dashboard refreshes must still cost nothing.
        for ($i = 0; $i < 10; $i++) {
            $this->withToken($user->createToken("t{$i}")->plainTextToken)
                ->getJson('/api/v1/ai/insights')->assertOk();
        }

        Http::assertNothingSent();
    }

    public function test_the_free_plan_also_receives_insights(): void
    {
        [$user] = $this->scenario('starter');

        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/v1/ai/insights')
            ->assertOk()
            ->assertJsonPath('data.meta.maxInsights', 2)
            ->assertJsonPath('data.meta.canRefreshOnDemand', false);
    }

    public function test_the_free_plan_cannot_refresh_on_demand(): void
    {
        Queue::fake();
        [$user] = $this->scenario('starter');

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/v1/ai/insights/refresh')
            ->assertForbidden()
            ->assertJsonPath('error.requiredFeature', 'ai.on_demand_refresh');

        Queue::assertNothingPushed();
    }

    public function test_paid_plans_can_queue_a_refresh_once_per_hour(): void
    {
        Queue::fake();
        [$user] = $this->scenario('pro');

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/v1/ai/insights/refresh')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true);

        Queue::assertPushed(GenerateFinancialInsights::class);
    }

    public function test_a_second_refresh_within_the_cooldown_is_refused(): void
    {
        Queue::fake();
        [$user] = $this->scenario('pro');
        $this->succeededRun($user);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/v1/ai/insights/refresh')
            ->assertStatus(422)
            ->assertJsonValidationErrors('refresh');

        Queue::assertNothingPushed();
    }

    public function test_generation_stores_prioritised_insights_with_laravel_derived_urls(): void
    {
        [$user, $overdueAmount] = $this->scenario('pro');
        $this->fakeAi([
            [
                'type' => 'receivable', 'severity' => 'critical',
                'title' => 'Piutang jatuh tempo menumpuk',
                'summary' => 'Beberapa tagihan sudah lewat jatuh tempo dan perlu ditagih pekan ini.',
                'evidence' => [['label' => 'Nilai jatuh tempo', 'value' => $overdueAmount]],
                'recommendedAction' => 'send_reminders',
            ],
            [
                'type' => 'growth', 'severity' => 'info',
                'title' => 'Pelanggan baru bertambah',
                'summary' => 'Ada pelanggan baru bulan ini.',
                'evidence' => [['label' => 'Pelanggan baru', 'value' => 1]],
                'recommendedAction' => 'no_action',
            ],
        ]);

        $insights = $this->app->make(FinancialInsightService::class)->generate($user);

        $this->assertCount(2, $insights);
        $this->assertSame('send_reminders', $insights[0]->recommended_action->value);
        $this->assertSame('/invoices?status=overdue', $insights[0]->action_url);
        $this->assertSame('pink', $insights[0]->severity->tone());
        $this->assertNull($insights[1]->action_url);
        $this->assertSame(1, $insights[0]->position);
        $this->assertDatabaseHas('ai_runs', ['feature' => 'financial_insight', 'status' => AiRunStatus::Succeeded->value]);
    }

    public function test_an_invented_number_is_rejected_and_nothing_is_stored(): void
    {
        [$user] = $this->scenario('pro');
        $this->fakeAi([[
            'type' => 'receivable', 'severity' => 'critical',
            'title' => 'Piutang naik drastis',
            'summary' => 'Angka ini tidak berasal dari data Laravel.',
            'evidence' => [['label' => 'Nilai karangan', 'value' => 987654321]],
            'recommendedAction' => 'send_reminders',
        ]]);

        try {
            $this->app->make(FinancialInsightService::class)->generate($user);
            $this->fail('Angka karangan seharusnya ditolak.');
        } catch (AiValidationException $exception) {
            $this->assertStringContainsString('987654321', implode(' ', $exception->violations));
        }

        $this->assertDatabaseCount('ai_insights', 0);
        $this->assertDatabaseHas('ai_runs', ['status' => AiRunStatus::InvalidOutput->value]);
    }

    public function test_the_plan_caps_how_many_insights_are_kept(): void
    {
        [$user, $overdueAmount] = $this->scenario('starter');
        $this->fakeAi(array_fill(0, 5, [
            'type' => 'receivable', 'severity' => 'warning',
            'title' => 'Tagihan perlu ditagih', 'summary' => 'Ada tagihan yang perlu perhatian.',
            'evidence' => [['label' => 'Nilai jatuh tempo', 'value' => $overdueAmount]],
            'recommendedAction' => 'send_reminders',
        ]));

        $insights = $this->app->make(FinancialInsightService::class)->generate($user);

        $this->assertCount(2, $insights);
    }

    public function test_regenerating_retires_the_previous_batch_instead_of_deleting_it(): void
    {
        [$user, $overdueAmount] = $this->scenario('pro');
        $old = $this->storedInsight($user);
        $this->fakeAi([[
            'type' => 'cashflow', 'severity' => 'warning', 'title' => 'Arus kas melambat',
            'summary' => 'Penerimaan pekan ini lebih rendah.',
            'evidence' => [['label' => 'Nilai jatuh tempo', 'value' => $overdueAmount]],
            'recommendedAction' => 'send_reminders',
        ]]);

        $this->app->make(FinancialInsightService::class)->generate($user);

        $this->assertDatabaseHas('ai_insights', ['id' => $old->id]);
        $this->assertTrue($old->refresh()->valid_until->lte(now()));
        $this->assertSame(1, AiInsight::query()->where('user_id', $user->id)->visible()->count());
    }

    public function test_an_insight_can_be_dismissed_and_is_scoped_to_its_owner(): void
    {
        Http::fake();
        [$user] = $this->scenario('basic');
        $insight = $this->storedInsight($user);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->deleteJson("/api/v1/ai/insights/{$insight->id}")
            ->assertOk();

        $this->assertNotNull($insight->refresh()->dismissed_at);
        $this->assertSame(0, AiInsight::query()->where('user_id', $user->id)->visible()->count());
    }

    public function test_another_users_insight_cannot_be_dismissed(): void
    {
        Http::fake();
        [$owner] = $this->scenario('basic');
        $insight = $this->storedInsight($owner);
        $intruder = User::factory()->create();
        $this->activatePlan($intruder, 'basic');

        $this->withToken($intruder->createToken('t')->plainTextToken)
            ->deleteJson("/api/v1/ai/insights/{$insight->id}")
            ->assertNotFound();

        $this->assertNull($insight->refresh()->dismissed_at);
    }

    public function test_expired_insights_disappear_from_the_dashboard(): void
    {
        Http::fake();
        [$user] = $this->scenario('basic');
        $insight = $this->storedInsight($user);
        $insight->update(['valid_until' => now()->subMinute()]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/v1/ai/insights')
            ->assertOk()
            ->assertJsonCount(0, 'data.insights');
    }

    public function test_the_ledger_is_never_sent_only_aggregate_metrics(): void
    {
        [$user, $overdueAmount] = $this->scenario('pro');
        Invoice::factory()->for($user, 'owner')->create([
            'number' => 'INV-RAHASIA-001', 'customer_name' => 'Pelanggan Rahasia',
            'customer_email' => 'rahasia@pelanggan.id', 'status' => Invoice::STATUS_UNPAID,
            'issue_date' => today()->subDay(), 'due_date' => today()->addMonth(),
            'subtotal' => 700000, 'total_amount' => 700000, 'paid_amount' => 0, 'balance_due' => 700000,
        ]);
        $this->fakeAi([[
            'type' => 'receivable', 'severity' => 'warning', 'title' => 'Tagihan perlu ditagih',
            'summary' => 'Ada tagihan yang perlu perhatian.',
            'evidence' => [['label' => 'Nilai jatuh tempo', 'value' => $overdueAmount]],
            'recommendedAction' => 'send_reminders',
        ]]);

        $this->app->make(FinancialInsightService::class)->generate($user);

        Http::assertSent(function (Request $request): bool {
            $body = json_encode($request->data());

            return ! str_contains($body, 'INV-RAHASIA-001')
                && ! str_contains($body, 'Pelanggan Rahasia')
                && ! str_contains($body, 'rahasia@pelanggan.id');
        });
    }

    public function test_the_scheduled_fan_out_skips_plans_without_insights_and_users_not_yet_due(): void
    {
        Queue::fake();
        [$due] = $this->scenario('pro');
        [$notDue] = $this->scenario('pro');
        $this->succeededRun($notDue);
        User::factory()->unverified()->create();

        (new DispatchFinancialInsightRefresh)->handle(
            $this->app->make(EntitlementService::class),
            $this->app->make(FinancialInsightService::class),
        );

        Queue::assertPushed(GenerateFinancialInsights::class, 1);
        Queue::assertPushed(fn (GenerateFinancialInsights $job): bool => $job->userId === $due->id);
    }

    public function test_the_fan_out_does_nothing_while_ai_is_switched_off(): void
    {
        Queue::fake();
        config()->set('ai.enabled', false);
        $this->scenario('pro');

        (new DispatchFinancialInsightRefresh)->handle(
            $this->app->make(EntitlementService::class),
            $this->app->make(FinancialInsightService::class),
        );

        Queue::assertNothingPushed();
    }

    public function test_the_job_notifies_the_owner_once_insights_are_ready(): void
    {
        [$user, $overdueAmount] = $this->scenario('pro');
        $this->fakeAi([[
            'type' => 'receivable', 'severity' => 'warning', 'title' => 'Tagihan perlu ditagih',
            'summary' => 'Ada tagihan yang perlu perhatian.',
            'evidence' => [['label' => 'Nilai jatuh tempo', 'value' => $overdueAmount]],
            'recommendedAction' => 'send_reminders',
        ]]);

        (new GenerateFinancialInsights($user->id))->handle(
            $this->app->make(FinancialInsightService::class),
            $this->app->make(NotificationService::class),
        );

        $this->assertDatabaseHas('notifications', ['type' => 'ai.insights_ready', 'notifiable_id' => $user->id]);
    }

    public function test_the_job_stays_quiet_when_ai_is_unavailable(): void
    {
        Http::fake();
        config()->set('ai.enabled', false);
        [$user] = $this->scenario('pro');

        (new GenerateFinancialInsights($user->id))->handle(
            $this->app->make(FinancialInsightService::class),
            $this->app->make(NotificationService::class),
        );

        $this->assertDatabaseCount('ai_insights', 0);
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
    }

    /** @return array{User, int} */
    private function scenario(string $plan): array
    {
        $user = User::factory()->create();
        $this->activatePlan($user, $plan);
        Invoice::factory()->for($user, 'owner')->create([
            'number' => 'INV-2026-001', 'status' => Invoice::STATUS_UNPAID,
            'issue_date' => today()->subDays(60), 'due_date' => today()->subDays(20),
            'subtotal' => 3420000, 'total_amount' => 3420000, 'paid_amount' => 0, 'balance_due' => 3420000,
        ]);

        return [$user, 3420000];
    }

    private function storedInsight(User $user): AiInsight
    {
        return AiInsight::query()->create([
            'user_id' => $user->id, 'type' => 'receivable', 'severity' => 'critical',
            'title' => 'Piutang jatuh tempo menumpuk', 'summary' => 'Perlu ditagih pekan ini.',
            'evidence' => [['label' => 'Nilai jatuh tempo', 'value' => 3420000]],
            'recommended_action' => 'send_reminders', 'action_url' => '/invoices?status=overdue',
            'position' => 1, 'valid_until' => now()->addDay(),
        ]);
    }

    private function succeededRun(User $user): void
    {
        AiRun::query()->create([
            'user_id' => $user->id, 'feature' => 'financial_insight', 'provider' => 'gemini',
            'model' => 'm', 'prompt_version' => 'v1', 'status' => AiRunStatus::Succeeded->value,
            'input_hash' => str_repeat('b', 64),
        ]);
    }

    /** @param list<array<string, mixed>> $insights */
    private function fakeAi(array $insights): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode(['insights' => $insights])]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 400, 'candidatesTokenCount' => 250, 'thoughtsTokenCount' => 0],
        ])]);
    }
}
