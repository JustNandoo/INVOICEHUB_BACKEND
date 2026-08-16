<?php

namespace Tests\Feature\Reconciliation;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\Reconciliation;
use App\Models\ReconciliationSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReconciliationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_api_requires_verified_authentication(): void
    {
        $this->getJson('/api/v1/reconciliation/summary')->assertUnauthorized();
        $user = User::factory()->unverified()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/reconciliation/summary')
            ->assertForbidden();
    }

    public function test_bank_account_list_and_sync_are_scoped_to_owner(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->for($user, 'owner')->create(['bank_code' => 'BCA', 'account_number_last_four' => '4412']);
        BankAccount::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/bank-accounts')
            ->assertOk()->assertJsonCount(1, 'data.accounts')
            ->assertJsonPath('data.accounts.0.accountNumberMasked', '•••• 4412');

        $this->withToken($token)->postJson("/api/v1/bank-accounts/{$account->id}/sync")
            ->assertOk()->assertJsonPath('data.account.connectionStatus', 'connected');
        $this->assertNotNull($account->refresh()->last_synced_at);
    }

    public function test_summary_and_transaction_filters_match_the_web_cards(): void
    {
        [$user, $account] = $this->ownerAndAccount();
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create(['amount' => 1000000, 'status' => 'matched']);
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create(['amount' => 448500, 'sender_name' => 'SRI WAHYUNI', 'status' => 'needs_confirmation']);
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create(['amount' => 75250, 'status' => 'unmatched']);
        BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create(['amount' => 50000, 'status' => 'ignored']);
        BankTransaction::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/reconciliation/summary')
            ->assertOk()->assertJsonPath('data.summary.totalTransactions', 4)
            ->assertJsonPath('data.summary.matchedTransactions', 1)
            ->assertJsonPath('data.summary.unmatchedTransactions', 1)
            ->assertJsonPath('data.summary.needsConfirmation', 1)
            ->assertJsonPath('data.summary.matchPercentage', 25)
            ->assertJsonPath('data.summary.totalIncomingAmount', 1573750);

        $this->withToken($token)->getJson('/api/v1/bank-transactions?status=needs_confirmation&search=Sri')
            ->assertOk()->assertJsonCount(1, 'data.transactions')
            ->assertJsonPath('data.transactions.0.senderName', 'SRI WAHYUNI');
    }

    public function test_rule_based_candidates_find_invoice_and_bank_fee_difference(): void
    {
        [$user, $account] = $this->ownerAndAccount();
        $invoice = $this->invoice($user, 450000, ['number' => 'INV-2026-042', 'customer_name' => 'Toko Budi']);
        $transaction = BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create([
            'amount' => 448500,
            'sender_name' => 'BUDI SETIAWAN',
            'description' => 'Pembayaran INV-2026-042',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson("/api/v1/bank-transactions/{$transaction->id}/candidates")
            ->assertOk()->assertJsonCount(1, 'data.candidates')
            ->assertJsonPath('data.candidates.0.invoice.id', $invoice->id)
            ->assertJsonPath('data.candidates.0.suggestedAppliedAmount', 450000)
            ->assertJsonPath('data.candidates.0.differenceAmount', 1500)
            ->assertJsonPath('data.candidates.0.differenceType', 'bank_fee')
            ->assertJsonPath('data.candidates.0.score', 100);

        $this->assertSame('needs_confirmation', $transaction->refresh()->status);
    }

    public function test_confirm_reconciliation_creates_payment_and_marks_invoice_paid(): void
    {
        [$user, $account] = $this->ownerAndAccount();
        $invoice = $this->invoice($user, 450000);
        $transaction = BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create(['amount' => 448500]);

        $response = $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/reconciliations', [
                'bankTransactionId' => $transaction->id,
                'invoiceId' => $invoice->id,
                'appliedAmount' => 450000,
                'adjustments' => [['type' => 'bank_fee', 'amount' => 1500, 'description' => 'Biaya admin BCA']],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.reconciliation.status', 'confirmed')
            ->assertJsonPath('data.reconciliation.invoice.status', 'paid')
            ->assertJsonPath('data.reconciliation.adjustments.0.amount', 1500);
        $this->assertDatabaseHas('invoice_payments', ['invoice_id' => $invoice->id, 'amount' => 450000, 'source' => 'reconciliation']);
        $this->assertDatabaseHas('bank_transactions', ['id' => $transaction->id, 'status' => 'matched']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'balance_due' => 0]);
    }

    public function test_same_transaction_cannot_be_reconciled_twice(): void
    {
        [$user, $account] = $this->ownerAndAccount();
        $firstInvoice = $this->invoice($user, 100000);
        $secondInvoice = $this->invoice($user, 100000, ['number' => 'INV-2026-099']);
        $transaction = BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create(['amount' => 100000]);
        $token = $user->createToken('test')->plainTextToken;
        $payload = ['bankTransactionId' => $transaction->id, 'invoiceId' => $firstInvoice->id, 'appliedAmount' => 100000];

        $this->withToken($token)->postJson('/api/v1/reconciliations', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/v1/reconciliations', [...$payload, 'invoiceId' => $secondInvoice->id])
            ->assertUnprocessable()->assertJsonValidationErrors('bankTransactionId');
    }

    public function test_reversal_voids_payment_and_restores_invoice_balance(): void
    {
        [$user, $account] = $this->ownerAndAccount();
        $invoice = $this->invoice($user, 300000);
        $transaction = BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create(['amount' => 300000]);
        $token = $user->createToken('test')->plainTextToken;
        $created = $this->withToken($token)->postJson('/api/v1/reconciliations', [
            'bankTransactionId' => $transaction->id, 'invoiceId' => $invoice->id, 'appliedAmount' => 300000,
        ])->assertCreated();
        $id = $created->json('data.reconciliation.id');

        $this->withToken($token)->postJson("/api/v1/reconciliations/{$id}/reverse", ['reason' => 'Invoice yang dipilih salah.'])
            ->assertOk()->assertJsonPath('data.reconciliation.status', 'reversed');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'unpaid', 'paid_amount' => 0, 'balance_due' => 300000]);
        $this->assertDatabaseHas('bank_transactions', ['id' => $transaction->id, 'status' => 'unmatched']);
        $this->assertNotNull(Reconciliation::query()->findOrFail($id)->payment->voided_at);
    }

    public function test_ignore_and_reject_candidate_flows_work(): void
    {
        [$user, $account] = $this->ownerAndAccount();
        $invoice = $this->invoice($user, 200000);
        $transaction = BankTransaction::factory()->for($user, 'owner')->for($account, 'bankAccount')->create(['amount' => 200000]);
        ReconciliationSuggestion::query()->create([
            'bank_transaction_id' => $transaction->id, 'invoice_id' => $invoice->id, 'score' => 70,
            'suggested_applied_amount' => 200000, 'difference_amount' => 0, 'reasons' => ['Nominal sama'], 'status' => 'pending',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/bank-transactions/{$transaction->id}/reject-candidate", [
            'invoiceId' => $invoice->id, 'reason' => 'Bukan pelanggan ini',
        ])->assertOk()->assertJsonPath('data.candidate.status', 'rejected');
        $this->assertSame('unmatched', $transaction->refresh()->status);

        $this->withToken($token)->postJson("/api/v1/bank-transactions/{$transaction->id}/ignore", ['reason' => 'Setoran modal'])
            ->assertOk()->assertJsonPath('data.transaction.status', 'ignored');
    }

    public function test_csv_import_is_private_validated_and_idempotent(): void
    {
        Storage::fake('local');
        [$user, $account] = $this->ownerAndAccount();
        $content = "Tanggal,Nominal,Pengirim,Keterangan,Referensi,ID Transaksi,Tipe\n"
            ."2026-08-16,448500,SRI WAHYUNI,Pembayaran invoice,TRF/BCA/8842,TX-001,credit\n"
            ."2026-08-16,75250,KASIR OVO,Transfer OVO,OVO-002,TX-002,credit\n";
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->post('/api/v1/bank-transactions/imports', [
            'bankAccountId' => $account->id,
            'file' => UploadedFile::fake()->createWithContent('mutasi.csv', $content),
        ], ['Accept' => 'application/json']);
        $response->assertCreated()->assertJsonPath('data.import.status', 'completed')
            ->assertJsonPath('data.import.importedRows', 2)->assertJsonPath('data.import.failedRows', 0);
        $this->assertDatabaseCount('bank_transactions', 2);
        Storage::disk('local')->assertExists(BankTransaction::query()->firstOrFail()->import->stored_path);

        $this->withToken($token)->post('/api/v1/bank-transactions/imports', [
            'bankAccountId' => $account->id,
            'file' => UploadedFile::fake()->createWithContent('mutasi-lagi.csv', $content),
        ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.import.importedRows', 0)->assertJsonPath('data.import.duplicateRows', 2);
        $this->assertDatabaseCount('bank_transactions', 2);
    }

    public function test_import_automatically_reconciles_an_exact_invoice_number_and_amount(): void
    {
        Storage::fake('local');
        [$user, $account] = $this->ownerAndAccount();
        $invoice = $this->invoice($user, 450000, ['number' => 'INV-2026-042']);
        $content = "Tanggal,Nominal,Pengirim,Keterangan,Referensi,ID Transaksi,Tipe\n"
            ."2026-08-16,450000,TOKO BUDI,Pembayaran INV-2026-042,INV-2026-042,TX-EXACT,credit\n";

        $this->withToken($user->createToken('test')->plainTextToken)
            ->post('/api/v1/bank-transactions/imports', [
                'bankAccountId' => $account->id,
                'file' => UploadedFile::fake()->createWithContent('exact.csv', $content),
            ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.import.importedRows', 1);

        $this->assertDatabaseHas('reconciliations', [
            'invoice_id' => $invoice->id, 'matched_by' => 'exact_rule', 'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'balance_due' => 0]);
        $this->assertDatabaseHas('bank_transactions', ['external_transaction_id' => 'TX-EXACT', 'status' => 'matched']);
    }

    public function test_other_users_cannot_access_transactions_or_reconciliations(): void
    {
        [$owner, $account] = $this->ownerAndAccount();
        $transaction = BankTransaction::factory()->for($owner, 'owner')->for($account, 'bankAccount')->create();
        $intruder = User::factory()->create();

        $this->withToken($intruder->createToken('test')->plainTextToken)
            ->getJson("/api/v1/bank-transactions/{$transaction->id}")->assertNotFound();
    }

    /** @return array{User, BankAccount} */
    private function ownerAndAccount(): array
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->for($user, 'owner')->create(['bank_code' => 'BCA']);

        return [$user, $account];
    }

    /** @param array<string, mixed> $overrides */
    private function invoice(User $user, int $amount, array $overrides = []): Invoice
    {
        return Invoice::factory()->for($user, 'owner')->create(array_merge([
            'number' => 'INV-2026-042', 'status' => Invoice::STATUS_UNPAID,
            'issue_date' => today()->subWeek(), 'due_date' => today()->addMonth(),
            'subtotal' => $amount, 'total_amount' => $amount, 'paid_amount' => 0, 'balance_due' => $amount,
        ], $overrides));
    }
}
