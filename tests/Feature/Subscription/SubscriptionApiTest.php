<?php

namespace Tests\Feature\Subscription;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_catalog_is_public_and_matches_the_product_cards(): void
    {
        $this->getJson('/api/v1/subscriptions/plans')
            ->assertOk()
            ->assertJsonCount(3, 'data.plans')
            ->assertJsonPath('data.plans.0.code', 'starter')
            ->assertJsonPath('data.plans.0.price', 0)
            ->assertJsonPath('data.plans.0.limits.monthlyInvoices', 10)
            ->assertJsonPath('data.plans.1.code', 'basic')
            ->assertJsonPath('data.plans.1.price', 99000)
            ->assertJsonPath('data.plans.2.code', 'pro')
            ->assertJsonPath('data.plans.2.price', 299000)
            ->assertJsonPath('data.plans.2.isRecommended', true);
    }

    public function test_verified_user_gets_starter_by_default_with_usage_and_no_fake_payments(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->count(2)->for($user, 'owner')->create();

        $this->withToken($user->createToken('subscription-test')->plainTextToken)
            ->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription.plan.code', 'starter')
            ->assertJsonPath('data.subscription.source', 'default')
            ->assertJsonPath('data.usage.monthlyInvoices.used', 2)
            ->assertJsonPath('data.usage.monthlyInvoices.limit', 10)
            ->assertJsonPath('data.usage.monthlyInvoices.remaining', 8)
            ->assertJsonPath('data.paymentsAvailable', false)
            ->assertJsonCount(0, 'data.paymentHistory');

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id, 'status' => 'active', 'source' => 'default',
        ]);
    }

    public function test_internal_activation_switches_entitlements_without_a_purchase_api(): void
    {
        $user = User::factory()->create();
        $this->activatePlan($user, 'basic');

        $response = $this->withToken($user->createToken('subscription-test')->plainTextToken)
            ->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription.plan.code', 'basic')
            ->assertJsonPath('data.usage.monthlyInvoices.unlimited', true);
        $this->assertTrue($response->json('data.subscription.plan.features')['bank.integration']);

        $this->postJson('/api/v1/subscription/purchase', ['plan' => 'pro'])->assertNotFound();
    }

    public function test_starter_is_blocked_from_paid_features_and_basic_can_access_them(): void
    {
        $starter = User::factory()->create();
        $starterToken = $starter->createToken('starter')->plainTextToken;

        $this->withToken($starterToken)->getJson('/api/v1/reconciliation/summary')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PLAN_UPGRADE_REQUIRED')
            ->assertJsonPath('error.currentPlan', 'starter');

        $this->activatePlan($starter, 'basic');
        $this->withToken($starterToken)->getJson('/api/v1/reconciliation/summary')->assertOk();
    }

    public function test_starter_monthly_invoice_limit_is_enforced(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->count(10)->for($user, 'owner')->create(['created_at' => now()]);

        $this->withToken($user->createToken('starter')->plainTextToken)
            ->postJson('/api/v1/invoices', [
                'customer' => ['name' => 'Toko Budi', 'email' => 'budi@example.com'],
                'issueDate' => today()->format('Y-m-d'),
                'dueDate' => today()->addMonth()->format('Y-m-d'),
                'items' => [['description' => 'Kopi 1kg', 'quantity' => 1, 'unitPrice' => 150000]],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PLAN_UPGRADE_REQUIRED')
            ->assertJsonPath('error.limit', 'monthlyInvoices:10');
    }
}
