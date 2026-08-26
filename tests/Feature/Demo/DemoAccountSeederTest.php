<?php

namespace Tests\Feature\Demo;

use App\Models\AiInsight;
use App\Models\FinancialAnomaly;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceOrder;
use App\Models\TaxPeriodReport;
use App\Models\User;
use App\Services\Invoice\InvoiceQueryService;
use App\Services\Reconciliation\ReconciliationQueryService;
use App\Services\Subscription\SubscriptionService;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_complete_and_repeatable_demo_account(): void
    {
        $this->seed(DemoAccountSeeder::class);
        $this->seed(DemoAccountSeeder::class);

        $user = User::query()->where('email', DemoAccountSeeder::EMAIL)->firstOrFail();

        $this->assertSame(1, User::query()->where('email', DemoAccountSeeder::EMAIL)->count());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check(DemoAccountSeeder::PASSWORD, $user->password));
        $this->assertSame('pro', app(SubscriptionService::class)->current($user)->plan->code);

        $this->assertSame(12, $user->customers()->count());
        $this->assertSame(35, $user->invoices()->count());
        $this->assertSame(3, $user->bankAccounts()->count());
        $this->assertSame(23, $user->bankTransactions()->where('type', 'credit')->count());
        $this->assertSame(20, $user->reconciliations()->count());
        $this->assertSame(6, $user->taxReports()->count());
        $this->assertSame(4, MarketplaceConnection::query()->where('user_id', $user->id)->count());
        $this->assertSame(8, MarketplaceOrder::query()->where('user_id', $user->id)->count());
        $this->assertSame(5, $user->notifications()->count());
        $this->assertSame(3, AiInsight::query()->where('user_id', $user->id)->count());
        $this->assertSame(3, FinancialAnomaly::query()->where('user_id', $user->id)->count());

        $invoiceSummary = app(InvoiceQueryService::class)->summary($user);
        $this->assertSame(8_750_000, $invoiceSummary['unpaidAmount']);
        $this->assertSame(2, $invoiceSummary['overdueInvoices']);

        $reconciliationSummary = app(ReconciliationQueryService::class)->summary($user);
        $this->assertSame(87, $reconciliationSummary['matchPercentage']);
        $this->assertSame(2, $reconciliationSummary['unmatchedTransactions']);
        $this->assertSame(1, $reconciliationSummary['needsConfirmation']);

        $currentTaxReport = TaxPeriodReport::query()->where([
            'user_id' => $user->id,
            'year' => now()->year,
            'month' => now()->month,
        ])->firstOrFail();
        $this->assertSame(48_750_000, $currentTaxReport->total_revenue);
        $this->assertSame(1_240_000, $currentTaxReport->pending_revenue);
        $this->assertSame(243_750, $currentTaxReport->estimated_tax);
    }
}
