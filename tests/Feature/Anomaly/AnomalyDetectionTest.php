<?php

namespace Tests\Feature\Anomaly;

use App\Enums\Anomaly\AnomalyStatus;
use App\Enums\Anomaly\AnomalyType;
use App\Exceptions\SubscriptionAccessDeniedException;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\FinancialAnomaly;
use App\Models\Invoice;
use App\Models\Reconciliation;
use App\Models\User;
use App\Services\Anomaly\AnomalyDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AnomalyDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_free_plan_has_no_leak_detection(): void
    {
        $user = User::factory()->create();
        $this->activatePlan($user, 'starter');

        $this->expectException(SubscriptionAccessDeniedException::class);

        $this->detector()->scan($user);
    }

    public function test_it_flags_incoming_money_that_was_never_matched(): void
    {
        [$user, $account] = $this->owner();
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => 750000, 'status' => BankTransaction::STATUS_UNMATCHED,
            'transaction_at' => now()->subDays(20),
        ]);
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => 750000, 'status' => BankTransaction::STATUS_UNMATCHED, 'transaction_at' => now()->subDay(),
        ]);
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => 10000, 'status' => BankTransaction::STATUS_UNMATCHED, 'transaction_at' => now()->subDays(20),
        ]);

        $result = $this->detector()->scan($user);

        $this->assertSame(1, $result['detected']);
        $this->assertSame(750000, $result['amountAtRisk']);
        $this->assertDatabaseHas('financial_anomalies', [
            'user_id' => $user->id, 'type' => AnomalyType::UnmatchedIncoming->value, 'amount_at_risk' => 750000,
        ]);
    }

    public function test_it_flags_a_marketplace_fee_that_is_out_of_proportion(): void
    {
        [$user, $account] = $this->owner();
        $reconciliation = $this->confirmedReconciliation($user, $account, applied: 200000);
        $reconciliation->adjustments()->create(['type' => 'marketplace_fee', 'amount' => 78000, 'description' => 'Potongan Shopee']);

        $anomalies = $this->scanFor($user, AnomalyType::ExcessiveFee);

        $this->assertCount(1, $anomalies);
        $this->assertSame(78000, $anomalies->first()->amount_at_risk);
        // JSON menyimpan 39.0 sebagai 39, jadi bandingkan nilainya, bukan tipenya.
        $this->assertEquals(39.0, $anomalies->first()->metadata['percentOfPayment']);
    }

    public function test_a_normal_bank_fee_is_left_alone(): void
    {
        [$user, $account] = $this->owner();
        $reconciliation = $this->confirmedReconciliation($user, $account, applied: 450000);
        $reconciliation->adjustments()->create(['type' => 'bank_fee', 'amount' => 1500, 'description' => 'Biaya admin BCA']);

        $this->assertCount(0, $this->scanFor($user, AnomalyType::ExcessiveFee));
    }

    public function test_it_flags_a_discount_above_the_configured_ceiling(): void
    {
        [$user] = $this->owner();
        $this->invoice($user, ['subtotal' => 500000, 'discount_amount' => 150000, 'total_amount' => 350000, 'balance_due' => 350000]);
        $this->invoice($user, ['number' => 'INV-OK', 'subtotal' => 500000, 'discount_amount' => 50000, 'total_amount' => 450000, 'balance_due' => 450000]);

        $anomalies = $this->scanFor($user, AnomalyType::ExcessiveDiscount);

        $this->assertCount(1, $anomalies);
        $this->assertEquals(30.0, $anomalies->first()->metadata['discountPercent']);
    }

    public function test_it_flags_a_leftover_balance_after_a_partial_reconciliation(): void
    {
        [$user, $account] = $this->owner();
        $invoice = $this->invoice($user, ['paid_amount' => 200000, 'balance_due' => 250000]);
        $this->confirmedReconciliation($user, $account, applied: 200000, invoice: $invoice, confirmedAt: now()->subDays(30));

        $anomalies = $this->scanFor($user, AnomalyType::UnsettledBalance);

        $this->assertCount(1, $anomalies);
        $this->assertSame(250000, $anomalies->first()->amount_at_risk);
    }

    public function test_it_flags_two_identical_payments_recorded_close_together(): void
    {
        [$user] = $this->owner();
        $invoice = $this->invoice($user);
        $invoice->payments()->create(['amount' => 450000, 'method' => 'bank_transfer', 'source' => 'manual', 'paid_at' => now()->subHours(3)]);
        $invoice->payments()->create(['amount' => 450000, 'method' => 'bank_transfer', 'source' => 'manual', 'paid_at' => now()->subHour()]);

        $anomalies = $this->scanFor($user, AnomalyType::DuplicatePayment);

        $this->assertCount(1, $anomalies);
        $this->assertSame(450000, $anomalies->first()->amount_at_risk);
        $this->assertSame('critical', $anomalies->first()->severity->value);
    }

    public function test_payments_far_apart_are_treated_as_instalments_not_duplicates(): void
    {
        [$user] = $this->owner();
        $invoice = $this->invoice($user);
        $invoice->payments()->create(['amount' => 200000, 'method' => 'bank_transfer', 'source' => 'manual', 'paid_at' => now()->subDays(30)]);
        $invoice->payments()->create(['amount' => 200000, 'method' => 'bank_transfer', 'source' => 'manual', 'paid_at' => now()->subDay()]);

        $this->assertCount(0, $this->scanFor($user, AnomalyType::DuplicatePayment));
    }

    public function test_it_flags_a_transaction_that_keeps_being_matched_and_unmatched(): void
    {
        [$user, $account] = $this->owner();
        $transaction = BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create();
        $invoice = $this->invoice($user);

        foreach (range(1, 2) as $ignored) {
            Reconciliation::query()->create([
                'user_id' => $user->id, 'bank_transaction_id' => $transaction->id, 'invoice_id' => $invoice->id,
                'matched_by' => 'manual', 'applied_amount' => 450000, 'status' => Reconciliation::STATUS_REVERSED,
                'confirmed_at' => now()->subDays(5), 'reversed_at' => now()->subDays(4), 'reversal_reason' => 'Salah cocok',
            ]);
        }

        $anomalies = $this->scanFor($user, AnomalyType::RepeatedReversal);

        $this->assertCount(1, $anomalies);
        $this->assertSame(2, $anomalies->first()->metadata['reversalCount']);
    }

    public function test_rescanning_updates_instead_of_duplicating(): void
    {
        [$user, $account] = $this->owner();
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => 750000, 'status' => BankTransaction::STATUS_UNMATCHED, 'transaction_at' => now()->subDays(20),
        ]);

        $first = $this->detector()->scan($user);
        $second = $this->detector()->scan($user);

        $this->assertSame(1, $first['detected']);
        $this->assertSame(0, $second['detected']);
        $this->assertDatabaseCount('financial_anomalies', 1);
    }

    public function test_a_finding_that_no_longer_reproduces_is_closed_automatically(): void
    {
        [$user, $account] = $this->owner();
        $transaction = BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => 750000, 'status' => BankTransaction::STATUS_UNMATCHED, 'transaction_at' => now()->subDays(20),
        ]);
        $this->detector()->scan($user);

        $transaction->update(['status' => BankTransaction::STATUS_MATCHED]);
        $result = $this->detector()->scan($user);

        $this->assertSame(1, $result['resolved']);
        $this->assertSame(0, $result['open']);
        $this->assertDatabaseHas('financial_anomalies', [
            'source_id' => $transaction->id, 'status' => AnomalyStatus::Resolved->value, 'resolution' => 'auto_resolved',
        ]);
    }

    public function test_a_finding_the_user_dismissed_is_never_reopened(): void
    {
        [$user, $account] = $this->owner();
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => 750000, 'status' => BankTransaction::STATUS_UNMATCHED, 'transaction_at' => now()->subDays(20),
        ]);
        $this->detector()->scan($user);
        FinancialAnomaly::query()->where('user_id', $user->id)
            ->update(['status' => AnomalyStatus::Dismissed->value, 'resolution' => 'not_an_issue']);

        $this->detector()->scan($user);

        $this->assertDatabaseHas('financial_anomalies', ['user_id' => $user->id, 'status' => AnomalyStatus::Dismissed->value]);
        $this->assertDatabaseCount('financial_anomalies', 1);
    }

    public function test_detection_is_scoped_per_user(): void
    {
        [$user] = $this->owner();
        [$other, $otherAccount] = $this->owner();
        BankTransaction::factory()->for($other, 'owner')->for($otherAccount, 'bankAccount')->create([
            'amount' => 750000, 'status' => BankTransaction::STATUS_UNMATCHED, 'transaction_at' => now()->subDays(20),
        ]);

        $result = $this->detector()->scan($user);

        $this->assertSame(0, $result['detected']);
        $this->assertDatabaseCount('financial_anomalies', 0);
    }

    private function detector(): AnomalyDetectionService
    {
        return $this->app->make(AnomalyDetectionService::class);
    }

    /** @return Collection<int, FinancialAnomaly> */
    private function scanFor(User $user, AnomalyType $type): Collection
    {
        $this->detector()->scan($user);

        return FinancialAnomaly::query()->where('user_id', $user->id)->where('type', $type->value)->get();
    }

    /** @return array{User, BankAccount} */
    private function owner(): array
    {
        $user = User::factory()->create();
        $this->activatePlan($user, 'pro');

        return [$user, BankAccount::factory()->for($user, 'owner')->create(['bank_code' => 'BCA'])];
    }

    /** @param array<string, mixed> $overrides */
    private function invoice(User $user, array $overrides = []): Invoice
    {
        return Invoice::factory()->for($user, 'owner')->create(array_merge([
            'number' => 'INV-2026-042', 'status' => Invoice::STATUS_UNPAID,
            'issue_date' => today()->subDays(10), 'due_date' => today()->addMonth(),
            'subtotal' => 450000, 'discount_amount' => 0, 'total_amount' => 450000,
            'paid_amount' => 0, 'balance_due' => 450000,
        ], $overrides));
    }

    private function confirmedReconciliation(
        User $user,
        BankAccount $account,
        int $applied,
        ?Invoice $invoice = null,
        ?\DateTimeInterface $confirmedAt = null,
    ): Reconciliation {
        $transaction = BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => $applied, 'status' => BankTransaction::STATUS_MATCHED,
        ]);
        $invoice ??= $this->invoice($user, ['number' => 'INV-'.$transaction->id]);

        return Reconciliation::query()->create([
            'user_id' => $user->id, 'bank_transaction_id' => $transaction->id, 'invoice_id' => $invoice->id,
            'matched_by' => 'manual', 'applied_amount' => $applied, 'status' => Reconciliation::STATUS_CONFIRMED,
            'confirmed_at' => $confirmedAt ?? now()->subDays(2),
        ]);
    }
}
