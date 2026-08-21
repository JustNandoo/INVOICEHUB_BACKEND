<?php

namespace Tests\Feature\Ai;

use App\Enums\Ai\AiRunStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AiReminderDraftApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.enabled', true);
        config()->set('ai.providers.gemini.key', 'test-key');
        Mail::fake();
    }

    public function test_it_requires_verified_authentication(): void
    {
        $this->postJson('/api/v1/invoices/1/ai-reminder-draft')->assertUnauthorized();

        $user = User::factory()->unverified()->create();
        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/v1/invoices/1/ai-reminder-draft')
            ->assertForbidden();
    }

    public function test_even_the_free_plan_can_generate_a_draft(): void
    {
        [$user, $invoice] = $this->scenario('starter');
        // Telat 12 hari, jadi nada otomatis yang diminta adalah "neutral".
        $this->fakeAi([['tone' => 'neutral', 'message' => $this->message($invoice)]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")
            ->assertOk()
            ->assertJsonPath('data.reminder.aiAvailable', true)
            ->assertJsonPath('data.reminder.drafts.0.tone', 'neutral')
            ->assertJsonCount(1, 'data.reminder.drafts');

        $this->assertDatabaseHas('ai_usage_records', ['user_id' => $user->id, 'credits_used' => 1]);
    }

    public function test_the_automatic_tone_hardens_with_how_late_the_invoice_is(): void
    {
        [$user, $invoice] = $this->scenario('starter');
        $invoice->update(['due_date' => today()->subDays(45)]);
        $this->fakeAi([['tone' => 'firm', 'message' => $this->message($invoice)]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")
            ->assertOk()
            ->assertJsonPath('data.reminder.drafts.0.tone', 'firm');
    }

    public function test_the_free_plan_cannot_pick_a_tone(): void
    {
        Http::fake();
        [$user, $invoice] = $this->scenario('starter');

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft", ['tone' => 'firm'])
            ->assertForbidden()
            ->assertJsonPath('error.requiredFeature', 'ai.reminder_tones');

        Http::assertNothingSent();
    }

    public function test_paid_plans_receive_three_tone_variants(): void
    {
        [$user, $invoice] = $this->scenario('basic');
        $this->fakeAi([
            ['tone' => 'polite', 'message' => $this->message($invoice)],
            ['tone' => 'neutral', 'message' => $this->message($invoice)],
            ['tone' => 'firm', 'message' => $this->message($invoice)],
        ]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")
            ->assertOk()
            ->assertJsonCount(3, 'data.reminder.drafts')
            ->assertJsonPath('data.reminder.drafts.2.tone', 'firm');
    }

    public function test_a_requested_tone_returns_only_that_variant(): void
    {
        [$user, $invoice] = $this->scenario('pro');
        $this->fakeAi([['tone' => 'firm', 'message' => $this->message($invoice)]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft", ['tone' => 'firm'])
            ->assertOk()
            ->assertJsonCount(1, 'data.reminder.drafts')
            ->assertJsonPath('data.reminder.drafts.0.tone', 'firm');

        Http::assertSent(fn (Request $request): bool => str_contains(
            $request->data()['contents'][0]['parts'][0]['text'], '"requestedTones": [
        "firm"
    ]',
        ));
    }

    public function test_generating_a_draft_never_sends_anything(): void
    {
        [$user, $invoice] = $this->scenario('basic');
        $this->fakeAi([['tone' => 'polite', 'message' => $this->message($invoice)]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")->assertOk();

        Mail::assertNothingSent();
        $this->assertNull($invoice->refresh()->sent_at);
        $this->assertDatabaseCount('invoice_activities', 0);
    }

    public function test_a_settled_invoice_cannot_be_chased(): void
    {
        Http::fake();
        [$user, $invoice] = $this->scenario('basic');
        $invoice->update(['status' => Invoice::STATUS_PAID, 'paid_amount' => 450000, 'balance_due' => 0]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")
            ->assertStatus(422)
            ->assertJsonValidationErrors('invoice');

        Http::assertNothingSent();
    }

    public function test_another_users_invoice_is_not_reachable(): void
    {
        Http::fake();
        [, $invoice] = $this->scenario('basic');
        $intruder = User::factory()->create();
        $this->activatePlan($intruder, 'basic');

        $this->withToken($intruder->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_a_draft_longer_than_the_delivery_limit_is_rejected(): void
    {
        [$user, $invoice] = $this->scenario('basic');
        $this->fakeAi([['tone' => 'polite', 'message' => $invoice->number.' '.str_repeat('a', 1200)]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")
            ->assertOk()
            ->assertJsonPath('data.reminder.source', 'template')
            ->assertJsonPath('data.reminder.aiAvailable', false);

        $this->assertDatabaseHas('ai_runs', ['status' => AiRunStatus::InvalidOutput->value]);
    }

    public function test_a_draft_that_omits_the_invoice_number_is_rejected(): void
    {
        [$user, $invoice] = $this->scenario('basic');
        $this->fakeAi([['tone' => 'polite', 'message' => 'Halo, mohon segera lakukan pembayaran. Terima kasih.']]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")
            ->assertOk()
            ->assertJsonPath('data.reminder.source', 'template');

        $this->assertDatabaseHas('ai_runs', ['status' => AiRunStatus::InvalidOutput->value]);
    }

    public function test_a_draft_left_as_a_template_placeholder_is_rejected(): void
    {
        [$user, $invoice] = $this->scenario('basic');
        $this->fakeAi([['tone' => 'polite', 'message' => "Halo {{nama}}, invoice {$invoice->number} belum dibayar."]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")
            ->assertOk()
            ->assertJsonPath('data.reminder.source', 'template');
    }

    public function test_it_falls_back_to_a_laravel_template_when_ai_is_off(): void
    {
        Http::fake();
        config()->set('ai.enabled', false);
        [$user, $invoice] = $this->scenario('basic');

        $response = $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")
            ->assertOk()
            ->assertJsonPath('data.reminder.aiAvailable', false)
            ->assertJsonPath('data.reminder.unavailableReason', 'feature_flag_off');

        $message = $response->json('data.reminder.drafts.0.message');
        $this->assertStringContainsString($invoice->number, $message);
        $this->assertStringContainsString('Rp 450.000', $message);
        Http::assertNothingSent();
    }

    public function test_customer_contact_details_are_never_sent_to_the_provider(): void
    {
        [$user, $invoice] = $this->scenario('basic');
        $invoice->update([
            'customer_email' => 'rahasia@pelanggan.id',
            'customer_whatsapp' => '628123456789',
            'customer_address' => 'Jl. Rahasia No. 1',
        ]);
        $this->fakeAi([['tone' => 'polite', 'message' => $this->message($invoice)]]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft")->assertOk();

        Http::assertSent(function (Request $request): bool {
            $body = json_encode($request->data());

            return ! str_contains($body, 'rahasia@pelanggan.id')
                && ! str_contains($body, '628123456789')
                && ! str_contains($body, 'Jl. Rahasia');
        });
    }

    public function test_the_draft_can_be_sent_through_the_existing_delivery_endpoint(): void
    {
        [$user, $invoice] = $this->scenario('basic');
        $this->fakeAi([['tone' => 'polite', 'subject' => 'Pengingat pembayaran', 'message' => $this->message($invoice)]]);
        $token = $user->createToken('t')->plainTextToken;

        $draft = $this->withToken($token)
            ->postJson("/api/v1/invoices/{$invoice->id}/ai-reminder-draft", ['channel' => 'email'])
            ->assertOk()
            ->json('data.reminder.drafts.0.message');

        $this->withToken($token)
            ->postJson("/api/v1/invoices/{$invoice->id}/send", ['channel' => 'email', 'message' => $draft])
            ->assertOk()
            ->assertJsonPath('data.delivery.channel', 'email');

        Mail::assertSentCount(1);
        $this->assertNotNull($invoice->refresh()->sent_at);
    }

    /** @return array{User, Invoice} */
    private function scenario(string $plan): array
    {
        $user = User::factory()->create();
        $this->activatePlan($user, $plan);
        $invoice = Invoice::factory()->for($user, 'owner')->create([
            'number' => 'INV-2026-042', 'customer_name' => 'Toko Budi',
            'customer_email' => 'budi@toko.test', 'status' => Invoice::STATUS_UNPAID,
            'issue_date' => today()->subDays(40), 'due_date' => today()->subDays(12),
            'subtotal' => 450000, 'total_amount' => 450000, 'paid_amount' => 0, 'balance_due' => 450000,
        ]);

        return [$user, $invoice];
    }

    private function message(Invoice $invoice): string
    {
        return "Halo Toko Budi, invoice {$invoice->number} sebesar Rp 450.000 sudah jatuh tempo. Mohon konfirmasinya. Terima kasih.";
    }

    /** @param list<array<string, mixed>> $drafts */
    private function fakeAi(array $drafts): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode(['drafts' => $drafts])]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 200, 'candidatesTokenCount' => 120, 'thoughtsTokenCount' => 0],
        ])]);
    }
}
