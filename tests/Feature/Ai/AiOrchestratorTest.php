<?php

namespace Tests\Feature\Ai;

use App\Enums\Ai\AiFeature;
use App\Enums\Ai\AiRunStatus;
use App\Exceptions\Ai\AiDisabledException;
use App\Exceptions\Ai\AiProviderException;
use App\Exceptions\Ai\AiQuotaExceededException;
use App\Exceptions\Ai\AiValidationException;
use App\Exceptions\SubscriptionAccessDeniedException;
use App\Models\AiUsageRecord;
use App\Models\User;
use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\Support\AiPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.enabled', true);
        config()->set('ai.providers.gemini.key', 'test-key');
        config()->set('ai.max_retries', 2);
    }

    public function test_it_stores_a_successful_run_and_records_usage(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->geminiBody('{"ok":true}', input: 120, output: 40))]);
        $user = $this->proUser();

        $run = $this->orchestrator()->runStructured($user, AiFeature::ReconciliationAnalysis, $this->prompt());

        $this->assertSame(AiRunStatus::Succeeded, $run->status);
        $this->assertSame(['ok' => true], $run->structured_output);
        $this->assertSame(120, $run->input_tokens);
        $this->assertSame(40, $run->output_tokens);
        $this->assertSame('gemini', $run->provider);
        $this->assertDatabaseHas('ai_usage_records', [
            'user_id' => $user->id,
            'feature' => AiFeature::ReconciliationAnalysis->value,
            'credits_used' => AiFeature::ReconciliationAnalysis->credits(),
        ]);
        Http::assertSentCount(1);
    }

    public function test_the_kill_switch_blocks_every_call_without_touching_the_provider(): void
    {
        Http::fake();
        config()->set('ai.enabled', false);

        $this->expectException(AiDisabledException::class);

        try {
            $this->orchestrator()->runStructured($this->proUser(), AiFeature::ReconciliationAnalysis, $this->prompt());
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_plan_without_the_feature_is_rejected_before_the_provider_is_called(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->activatePlan($user, 'starter');

        $this->expectException(SubscriptionAccessDeniedException::class);

        try {
            $this->orchestrator()->runStructured($user, AiFeature::ReconciliationAnalysis, $this->prompt());
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_daily_credit_quota_is_enforced(): void
    {
        Http::fake();
        $user = $this->proUser();
        $this->burnCredits($user, (int) $user->subscriptions()->first()->plan->limit('aiCreditsDaily'));

        try {
            $this->orchestrator()->runStructured($user, AiFeature::ReconciliationAnalysis, $this->prompt());
            $this->fail('Kuota harian seharusnya menolak permintaan.');
        } catch (AiQuotaExceededException $exception) {
            $this->assertSame('daily', $exception->scope);
            $this->assertSame(429, $exception->render()->getStatusCode());
        }

        Http::assertNothingSent();
    }

    public function test_the_global_daily_cost_limit_disables_ai_for_everyone(): void
    {
        Http::fake();
        config()->set('ai.daily_cost_limit_idr', 1000);
        AiUsageRecord::query()->create([
            'user_id' => User::factory()->create()->id, 'feature' => 'financial_insight',
            'model' => 'x', 'estimated_cost' => 1500, 'credits_used' => 0,
        ]);

        $this->expectException(AiDisabledException::class);

        try {
            $this->orchestrator()->runStructured($this->proUser(), AiFeature::ReconciliationAnalysis, $this->prompt());
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_transient_provider_errors_are_retried_and_then_succeed(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['message' => 'high demand']], 503)
            ->push($this->geminiBody('{"ok":true}'), 200);

        $run = $this->orchestrator()->runStructured($this->proUser(), AiFeature::ReconciliationAnalysis, $this->prompt());

        $this->assertSame(AiRunStatus::Succeeded, $run->status);
        Http::assertSentCount(2);
    }

    public function test_unauthorized_responses_are_not_retried(): void
    {
        Http::fake([$this->endpoint() => Http::response(['error' => ['message' => 'bad key']], 401)]);

        try {
            $this->orchestrator()->runStructured($this->proUser(), AiFeature::ReconciliationAnalysis, $this->prompt());
            $this->fail('Seharusnya melempar AiProviderException.');
        } catch (AiProviderException $exception) {
            $this->assertSame('unauthorized', $exception->errorCode);
        }

        Http::assertSentCount(1);
        $this->assertDatabaseHas('ai_runs', ['status' => AiRunStatus::Failed->value, 'error_code' => 'unauthorized']);
    }

    public function test_truncated_output_is_retried_because_thinking_tokens_can_exhaust_the_budget(): void
    {
        Http::fakeSequence()
            ->push($this->geminiBody('{"ok":tr', finishReason: 'MAX_TOKENS'), 200)
            ->push($this->geminiBody('{"ok":true}'), 200);

        $run = $this->orchestrator()->runStructured($this->proUser(), AiFeature::ReconciliationAnalysis, $this->prompt());

        $this->assertSame(AiRunStatus::Succeeded, $run->status);
        Http::assertSentCount(2);
    }

    public function test_output_failing_validation_is_retried_then_stored_as_invalid_and_never_as_success(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->geminiBody('{"invoiceId":999}'))]);

        try {
            $this->orchestrator()->runStructured(
                $this->proUser(), AiFeature::ReconciliationAnalysis, $this->prompt(),
                function (array $payload): array {
                    throw new AiValidationException(['invoiceId di luar kandidat']);
                },
            );
            $this->fail('Seharusnya melempar AiValidationException.');
        } catch (AiValidationException $exception) {
            $this->assertContains('invoiceId di luar kandidat', $exception->violations);
        }

        Http::assertSentCount(3); // 1 percobaan + 2 retry
        $this->assertDatabaseHas('ai_runs', ['status' => AiRunStatus::InvalidOutput->value]);
        $this->assertDatabaseMissing('ai_runs', ['status' => AiRunStatus::Succeeded->value]);
    }

    public function test_identical_input_within_the_cache_window_reuses_the_stored_run(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->geminiBody('{"ok":true}'))]);
        $user = $this->proUser();

        $first = $this->orchestrator()->runStructured($user, AiFeature::ReconciliationAnalysis, $this->prompt());
        $second = $this->orchestrator()->runStructured($user, AiFeature::ReconciliationAnalysis, $this->prompt());

        $this->assertSame($first->id, $second->id);
        Http::assertSentCount(1);
        $this->assertSame(1, AiUsageRecord::query()->where('user_id', $user->id)->count());
    }

    public function test_the_request_pins_the_output_budget_and_translates_the_schema(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->geminiBody('{"ok":true}'))]);

        $this->orchestrator()->runStructured($this->proUser(), AiFeature::ReconciliationAnalysis, $this->prompt());

        Http::assertSent(function (Request $request): bool {
            $config = $request->data()['generationConfig'];

            return $config['maxOutputTokens'] === AiFeature::ReconciliationAnalysis->maxOutputTokens()
                && $config['responseMimeType'] === 'application/json'
                && $config['responseSchema']['type'] === 'OBJECT'
                && $config['responseSchema']['properties']['ok']['type'] === 'BOOLEAN';
        });
    }

    public function test_a_zero_thinking_budget_is_omitted_because_gemini_rejects_it(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->geminiBody('{"ok":true}'))]);
        config()->set('ai.features.reconciliation_analysis.thinking_budget', 0);

        $this->orchestrator()->runStructured($this->proUser(), AiFeature::ReconciliationAnalysis, $this->prompt());

        Http::assertSent(fn (Request $request): bool => ! isset($request->data()['generationConfig']['thinkingConfig']));
    }

    public function test_a_positive_thinking_budget_is_sent_through(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->geminiBody('{"ok":true}'))]);
        config()->set('ai.features.reconciliation_analysis.thinking_budget', 256);

        $this->orchestrator()->runStructured($this->proUser(), AiFeature::ReconciliationAnalysis, $this->prompt());

        Http::assertSent(fn (Request $request): bool => $request->data()['generationConfig']['thinkingConfig']['thinkingBudget'] === 256);
    }

    private function orchestrator(): AiOrchestrator
    {
        return $this->app->make(AiOrchestrator::class);
    }

    private function proUser(): User
    {
        $user = User::factory()->create();
        $this->activatePlan($user, 'pro');

        return $user;
    }

    private function burnCredits(User $user, int $credits): void
    {
        AiUsageRecord::query()->create([
            'user_id' => $user->id, 'feature' => 'financial_insight',
            'model' => 'x', 'credits_used' => $credits, 'estimated_cost' => 0,
        ]);
    }

    private function prompt(string $marker = 'a'): AiPrompt
    {
        return new AiPrompt(
            systemInstruction: 'Anda asisten uji.',
            userContent: 'Data uji '.$marker,
            schema: ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok']],
            hashPayload: ['marker' => $marker],
        );
    }

    private function endpoint(): string
    {
        return 'generativelanguage.googleapis.com/*';
    }

    /** @return array<string, mixed> */
    private function geminiBody(string $text, string $finishReason = 'STOP', int $input = 100, int $output = 30): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => $text]]],
                'finishReason' => $finishReason,
            ]],
            'usageMetadata' => [
                'promptTokenCount' => $input,
                'candidatesTokenCount' => $output,
                'thoughtsTokenCount' => 0,
                'totalTokenCount' => $input + $output,
            ],
        ];
    }
}
