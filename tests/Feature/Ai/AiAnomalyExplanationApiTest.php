<?php

namespace Tests\Feature\Ai;

use App\Enums\Ai\AiRunStatus;
use App\Enums\Anomaly\AnomalyStatus;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\FinancialAnomaly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAnomalyExplanationApiTest extends TestCase
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
        $this->postJson('/api/v1/anomalies/1/ai-explanation')->assertUnauthorized();

        $user = User::factory()->unverified()->create();
        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/v1/anomalies/1/ai-explanation')->assertForbidden();
    }

    public function test_the_free_plan_cannot_reach_leak_detection_at_all(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->activatePlan($user, 'starter');

        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/v1/anomalies')
            ->assertForbidden()
            ->assertJsonPath('error.requiredFeature', 'anomaly.detection');

        Http::assertNothingSent();
    }

    public function test_listing_and_summary_never_call_the_ai_provider(): void
    {
        Http::fake();
        [$user, $anomaly] = $this->scenario();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/v1/anomalies')
            ->assertOk()
            ->assertJsonCount(1, 'data.anomalies')
            ->assertJsonPath('data.anomalies.0.id', $anomaly->id)
            ->assertJsonPath('data.anomalies.0.explanation', null)
            ->assertJsonPath('data.anomalies.0.tone', 'yellow');

        $this->withToken($user->createToken('t2')->plainTextToken)
            ->getJson('/api/v1/anomalies/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.openCount', 1)
            ->assertJsonPath('data.summary.amountAtRisk', 750000)
            ->assertJsonPath('data.summary.explainedCount', 0);

        Http::assertNothingSent();
    }

    public function test_scanning_is_deterministic_and_costs_nothing(): void
    {
        Http::fake();
        [$user, $account] = $this->owner();
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => 900000, 'status' => BankTransaction::STATUS_UNMATCHED, 'transaction_at' => now()->subDays(15),
        ]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/v1/anomalies/scan')
            ->assertOk()
            ->assertJsonPath('data.scan.detected', 1)
            ->assertJsonPath('data.scan.amountAtRisk', 900000);

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_runs', 0);
    }

    public function test_it_explains_a_finding_and_stores_the_result(): void
    {
        [$user, $anomaly] = $this->scenario();
        $this->fakeAi([
            'explanation' => 'Uang Rp 750.000 sudah masuk rekening tetapi belum dikaitkan ke tagihan mana pun, sehingga pemasukan ini belum tercatat.',
            'likelyCause' => 'Pelanggan membayar tanpa mencantumkan nomor invoice.',
            'preventionTip' => 'Minta pelanggan menuliskan nomor invoice pada berita transfer.',
            'recommendedAction' => 'reconcile_manually',
            'confidence' => 88,
        ]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/anomalies/{$anomaly->id}/ai-explanation")
            ->assertOk()
            ->assertJsonPath('data.explanation.source', 'ai')
            ->assertJsonPath('data.explanation.recommendedAction', 'reconcile_manually')
            ->assertJsonPath('data.explanation.actionLabel', 'Cocokkan Manual')
            ->assertJsonPath('data.explanation.actionUrl', '/reconciliation')
            ->assertJsonPath('data.explanation.confidence', 88);

        $this->assertNotNull($anomaly->refresh()->explanation);
        $this->assertNotNull($anomaly->explained_at);
        $this->assertDatabaseHas('ai_runs', ['feature' => 'anomaly_explanation', 'status' => AiRunStatus::Succeeded->value]);
    }

    public function test_an_invented_amount_in_the_explanation_is_rejected(): void
    {
        [$user, $anomaly] = $this->scenario();
        $this->fakeAi([
            'explanation' => 'Anda kehilangan Rp 12.345.678 dari transaksi ini.',
            'likelyCause' => 'Angka ini tidak ada pada data temuan.',
            'preventionTip' => 'Periksa kembali catatan.',
            'recommendedAction' => 'review_transaction',
            'confidence' => 90,
        ]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/anomalies/{$anomaly->id}/ai-explanation")
            ->assertOk()
            ->assertJsonPath('data.explanation.aiAvailable', false)
            ->assertJsonPath('data.explanation.source', 'rules');

        $this->assertNull($anomaly->refresh()->explanation);
        $this->assertDatabaseHas('ai_runs', ['status' => AiRunStatus::InvalidOutput->value]);
    }

    public function test_dates_and_counts_in_the_prose_are_not_mistaken_for_invented_amounts(): void
    {
        [$user, $anomaly] = $this->scenario();
        $this->fakeAi([
            'explanation' => 'Uang sebesar Rp 750.000 yang masuk pada 29 Juli 2026 belum dikaitkan ke tagihan mana pun.',
            'likelyCause' => 'Sudah 20 hari transfer ini menganggur sejak 2026-07-29.',
            'preventionTip' => 'Cocokkan mutasi setiap 7 hari sekali.',
            'recommendedAction' => 'reconcile_manually', 'confidence' => 85,
        ]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/anomalies/{$anomaly->id}/ai-explanation")
            ->assertOk()
            ->assertJsonPath('data.explanation.source', 'ai');

        $this->assertNotNull($anomaly->refresh()->explanation);
    }

    public function test_an_action_outside_the_allowlist_is_rejected(): void
    {
        [$user, $anomaly] = $this->scenario();
        $this->fakeAi([
            'explanation' => 'Penjelasan wajar tanpa angka baru.',
            'likelyCause' => 'Penyebab wajar.',
            'preventionTip' => 'Saran wajar.',
            'recommendedAction' => 'delete_invoice',
            'confidence' => 90,
        ]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/anomalies/{$anomaly->id}/ai-explanation")
            ->assertOk()
            ->assertJsonPath('data.explanation.source', 'rules');
    }

    public function test_a_stored_explanation_is_reused_without_calling_the_provider_again(): void
    {
        [$user, $anomaly] = $this->scenario();
        $this->fakeAi([
            'explanation' => 'Uang Rp 750.000 belum dikaitkan ke tagihan.',
            'likelyCause' => 'Transfer tanpa nomor invoice.',
            'preventionTip' => 'Minta nomor invoice pada berita transfer.',
            'recommendedAction' => 'reconcile_manually', 'confidence' => 88,
        ]);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/anomalies/{$anomaly->id}/ai-explanation")->assertOk();
        $this->withToken($token)->postJson("/api/v1/anomalies/{$anomaly->id}/ai-explanation")
            ->assertOk()
            ->assertJsonPath('data.explanation.source', 'cache');

        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_runs', 1);
    }

    public function test_it_falls_back_to_the_deterministic_finding_when_ai_is_off(): void
    {
        Http::fake();
        config()->set('ai.enabled', false);
        [$user, $anomaly] = $this->scenario();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/anomalies/{$anomaly->id}/ai-explanation")
            ->assertOk()
            ->assertJsonPath('data.explanation.aiAvailable', false)
            ->assertJsonPath('data.explanation.unavailableReason', 'feature_flag_off')
            ->assertJsonPath('data.explanation.explanation', $anomaly->description);

        Http::assertNothingSent();
    }

    public function test_another_users_anomaly_is_not_reachable(): void
    {
        Http::fake();
        [, $anomaly] = $this->scenario();
        $intruder = User::factory()->create();
        $this->activatePlan($intruder, 'pro');

        $this->withToken($intruder->createToken('t')->plainTextToken)
            ->postJson("/api/v1/anomalies/{$anomaly->id}/ai-explanation")
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_a_finding_can_be_resolved_by_the_owner(): void
    {
        Http::fake();
        [$user, $anomaly] = $this->scenario();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/anomalies/{$anomaly->id}/resolve", ['resolution' => 'fixed', 'notes' => 'Sudah dicocokkan manual'])
            ->assertOk()
            ->assertJsonPath('data.anomaly.status', AnomalyStatus::Resolved->value);

        $this->assertNotNull($anomaly->refresh()->resolved_at);
    }

    public function test_the_prompt_carries_no_personal_contact_details(): void
    {
        [$user, $anomaly] = $this->scenario();
        $anomaly->update(['metadata' => [
            'senderName' => 'Sri Wahyuni', 'email' => 'rahasia@pelanggan.id', 'whatsapp' => '628123456789',
        ]]);
        $this->fakeAi([
            'explanation' => 'Uang Rp 750.000 belum dikaitkan ke tagihan.',
            'likelyCause' => 'Transfer tanpa nomor invoice.',
            'preventionTip' => 'Minta nomor invoice pada berita transfer.',
            'recommendedAction' => 'reconcile_manually', 'confidence' => 80,
        ]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/anomalies/{$anomaly->id}/ai-explanation")->assertOk();

        Http::assertSent(function (Request $request): bool {
            $body = json_encode($request->data());

            return ! str_contains($body, 'rahasia@pelanggan.id') && ! str_contains($body, '628123456789');
        });
    }

    /** @return array{User, BankAccount} */
    private function owner(): array
    {
        $user = User::factory()->create();
        $this->activatePlan($user, 'pro');

        return [$user, BankAccount::factory()->for($user, 'owner')->create(['bank_code' => 'BCA'])];
    }

    /** @return array{User, FinancialAnomaly} */
    private function scenario(): array
    {
        [$user, $account] = $this->owner();
        $transaction = BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => 750000, 'status' => BankTransaction::STATUS_UNMATCHED, 'transaction_at' => now()->subDays(20),
        ]);

        $anomaly = FinancialAnomaly::query()->create([
            'user_id' => $user->id, 'type' => 'unmatched_incoming', 'severity' => 'warning', 'status' => 'open',
            'title' => 'Transfer masuk Rp 750.000 belum tercocokkan',
            'description' => 'Uang masuk belum dikaitkan ke invoice mana pun.',
            'amount_at_risk' => 750000, 'source_type' => 'bank_transaction', 'source_id' => $transaction->id,
            'metadata' => ['ageDays' => 20], 'detected_at' => now(),
        ]);

        return [$user, $anomaly];
    }

    /** @param array<string, mixed> $output */
    private function fakeAi(array $output): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode($output)]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 300, 'candidatesTokenCount' => 150, 'thoughtsTokenCount' => 0],
        ])]);
    }
}
