<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-TESTKEY';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => self::SERVER_KEY,
            'services.midtrans.client_key' => 'SB-Mid-client-TESTKEY',
            'services.midtrans.is_production' => false,
            'services.midtrans.finish_url' => 'http://localhost:5174/settings/subscription',
        ]);
    }

    public function test_checkout_creates_a_pending_payment_and_returns_a_snap_token(): void
    {
        Http::fake(['*app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token-abc', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/abc',
        ])]);
        $user = User::factory()->create();

        $this->withToken($user->createToken('checkout-test')->plainTextToken)
            ->postJson('/api/v1/subscription/checkout', ['planCode' => 'basic'])
            ->assertCreated()
            ->assertJsonPath('data.checkout.snapToken', 'snap-token-abc')
            ->assertJsonPath('data.checkout.amount', 99000)
            ->assertJsonPath('data.checkout.isProduction', false)
            ->assertJsonPath('data.checkout.clientKey', 'SB-Mid-client-TESTKEY');

        // Belum lunas, jadi paket harus tetap starter.
        $this->assertDatabaseHas('subscription_payments', [
            'user_id' => $user->id, 'status' => 'pending', 'amount' => 99000,
        ]);
        $this->assertSame('starter', $this->currentPlanCode($user));
    }

    public function test_checkout_price_comes_from_the_catalog_not_the_client(): void
    {
        Http::fake(['*' => Http::response(['token' => 'snap-token-abc'])]);
        $user = User::factory()->create();

        $this->withToken($user->createToken('checkout-test')->plainTextToken)
            ->postJson('/api/v1/subscription/checkout', ['planCode' => 'pro', 'amount' => 1000])
            ->assertCreated()
            ->assertJsonPath('data.checkout.amount', 299000);
    }

    public function test_free_plan_cannot_be_purchased(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('checkout-test')->plainTextToken)
            ->postJson('/api/v1/subscription/checkout', ['planCode' => 'starter'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_PLAN_NOT_PURCHASABLE');
    }

    public function test_checkout_is_unavailable_when_midtrans_keys_are_missing(): void
    {
        config(['services.midtrans.server_key' => null, 'services.midtrans.client_key' => null]);
        $user = User::factory()->create();

        $this->withToken($user->createToken('checkout-test')->plainTextToken)
            ->postJson('/api/v1/subscription/checkout', ['planCode' => 'basic'])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'PAYMENT_NOT_CONFIGURED');
    }

    public function test_a_forged_notification_never_activates_a_plan(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson('/api/v1/webhooks/midtrans', [
            'order_id' => $payment->order_id,
            'status_code' => '200',
            'gross_amount' => '99000.00',
            'transaction_status' => 'settlement',
            'signature_key' => str_repeat('a', 128),
        ])->assertStatus(403)->assertJsonPath('error.code', 'PAYMENT_INVALID_SIGNATURE');

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame('starter', $this->currentPlanCode($payment->user));
    }

    public function test_a_signed_settlement_activates_the_plan(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson('/api/v1/webhooks/midtrans', $this->notification($payment))
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('basic', $this->currentPlanCode($payment->user));
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $payment->user_id, 'status' => 'active', 'source' => 'midtrans',
        ]);
    }

    public function test_repeated_notifications_do_not_activate_the_plan_twice(): void
    {
        $payment = $this->pendingPayment();
        $notification = $this->notification($payment);

        $this->postJson('/api/v1/webhooks/midtrans', $notification)->assertOk();
        $first = $this->currentPeriodEnd($payment->user);

        $this->postJson('/api/v1/webhooks/midtrans', $notification)->assertOk();

        $this->assertSame($first, $this->currentPeriodEnd($payment->user));
        $this->assertSame(1, $payment->user->subscriptions()->where('status', 'active')->count());
    }

    public function test_a_notification_whose_amount_does_not_match_is_rejected(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson('/api/v1/webhooks/midtrans', $this->notification($payment, grossAmount: '1000.00'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_AMOUNT_MISMATCH');

        $this->assertSame('starter', $this->currentPlanCode($payment->user));
    }

    public function test_an_expired_transaction_is_recorded_without_activating(): void
    {
        $payment = $this->pendingPayment();

        $this->postJson('/api/v1/webhooks/midtrans', $this->notification($payment, status: 'expire', statusCode: '407'))
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');

        $this->assertSame('starter', $this->currentPlanCode($payment->user));
    }

    public function test_payment_history_lists_settled_payments_only(): void
    {
        $payment = $this->pendingPayment();
        $token = $payment->user->createToken('history-test')->plainTextToken;

        // Selama masih pending, riwayat harus kosong.
        $this->withToken($token)->getJson('/api/v1/subscription/payment-history')
            ->assertOk()
            ->assertJsonPath('data.paymentsAvailable', true)
            ->assertJsonCount(0, 'data.payments');

        $this->postJson('/api/v1/webhooks/midtrans', $this->notification($payment))->assertOk();

        $this->withToken($token)->getJson('/api/v1/subscription/payment-history')
            ->assertOk()
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.status', 'paid')
            ->assertJsonPath('data.payments.0.statusLabel', 'Lunas')
            ->assertJsonPath('data.payments.0.amount', 99000)
            ->assertJsonPath('data.payments.0.planName', 'Basic');
    }

    private function pendingPayment(string $planCode = 'basic'): SubscriptionPayment
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('code', $planCode)->firstOrFail();

        return SubscriptionPayment::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'order_id' => 'SUB-TEST-'.$user->id,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'amount' => $plan->price,
            'provider' => 'midtrans',
            'snap_token' => 'snap-token-abc',
            'expires_at' => now()->addHour(),
        ]);
    }

    /** @return array<string, string> */
    private function notification(
        SubscriptionPayment $payment,
        string $status = 'settlement',
        string $statusCode = '200',
        ?string $grossAmount = null,
    ): array {
        $grossAmount ??= number_format($payment->amount, 2, '.', '');

        return [
            'order_id' => $payment->order_id,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => $status,
            'transaction_id' => 'trx-'.$payment->id,
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
            'signature_key' => hash('sha512', $payment->order_id.$statusCode.$grossAmount.self::SERVER_KEY),
        ];
    }

    private function currentPlanCode(User $user): string
    {
        return $user->subscriptions()->with('plan')->where('status', 'active')
            ->latest('starts_at')->first()?->plan->code ?? 'starter';
    }

    private function currentPeriodEnd(User $user): ?string
    {
        return $user->subscriptions()->where('status', 'active')->latest('starts_at')
            ->first()?->current_period_ends_at?->toIso8601String();
    }
}
