<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankTransactionImport;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Reconciliation;
use App\Models\ReconciliationSuggestion;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', DemoAccountSeeder::email())->firstOrFail();
        [$accounts, $imports] = $this->createBankAccounts($user);
        $this->createMatchedTransactions($user, $accounts, $imports);
        $this->createReviewTransactions($user, $accounts[0], $imports[$accounts[0]->id]);
    }

    /** @return array{list<BankAccount>, array<int, BankTransactionImport>} */
    private function createBankAccounts(User $user): array
    {
        $fixtures = [
            ['code' => 'BCA', 'name' => 'Bank Central Asia', 'number' => '1230888842', 'balance' => 100_000_000],
            ['code' => 'BNI', 'name' => 'Bank Negara Indonesia', 'number' => '9876999901', 'balance' => 60_000_000],
            ['code' => 'BRI', 'name' => 'Bank Rakyat Indonesia', 'number' => '6543111120', 'balance' => 20_000_000],
        ];
        $accounts = [];
        $imports = [];

        foreach ($fixtures as $index => $fixture) {
            $account = BankAccount::query()->create([
                'user_id' => $user->id,
                'bank_code' => $fixture['code'],
                'bank_name' => $fixture['name'],
                'account_holder' => 'CV Kopi Karsa Nusantara',
                'account_number' => $fixture['number'],
                'account_number_last_four' => substr($fixture['number'], -4),
                'account_number_fingerprint' => hash('sha256', $fixture['code'].'|'.$fixture['number']),
                'balance' => $fixture['balance'],
                'connection_status' => 'connected',
                'last_synced_at' => now()->subMinutes(10 + ($index * 5)),
            ]);
            $accounts[] = $account;

            $imports[$account->id] = BankTransactionImport::query()->create([
                'user_id' => $user->id,
                'bank_account_id' => $account->id,
                'original_filename' => strtolower($fixture['code']).'-mutasi-demo.csv',
                'stored_path' => 'demo/imports/'.strtolower($fixture['code']).'-mutasi-demo.csv',
                'status' => 'completed',
                'total_rows' => 25,
                'imported_rows' => 23,
                'duplicate_rows' => 2,
                'failed_rows' => 0,
                'errors' => [],
                'completed_at' => now()->subMinutes(9 + ($index * 5)),
            ]);
        }

        return [$accounts, $imports];
    }

    /** @param list<BankAccount> $accounts @param array<int, BankTransactionImport> $imports */
    private function createMatchedTransactions(User $user, array $accounts, array $imports): void
    {
        $payments = InvoicePayment::query()
            ->with('invoice')
            ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get();

        foreach ($payments as $index => $payment) {
            $account = $accounts[$index % count($accounts)];
            $hasBankFee = $index === 0;
            $transactionAmount = $hasBankFee ? $payment->amount - 1_500 : $payment->amount;
            $transaction = BankTransaction::query()->create([
                'user_id' => $user->id,
                'bank_account_id' => $account->id,
                'bank_transaction_import_id' => $imports[$account->id]->id,
                'external_transaction_id' => 'DEMO-TXN-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'fingerprint' => hash('sha256', 'demo-matched-'.$payment->id),
                'type' => 'credit',
                'amount' => $transactionAmount,
                'sender_name' => $payment->invoice->customer_name,
                'description' => 'Transfer pembayaran '.$payment->invoice->number,
                'reference' => $payment->invoice->number,
                'transaction_at' => $payment->paid_at,
                'status' => BankTransaction::STATUS_MATCHED,
                'raw_payload' => ['demo' => true, 'channel' => 'bank_api'],
            ]);

            $reconciliation = Reconciliation::query()->create([
                'user_id' => $user->id,
                'bank_transaction_id' => $transaction->id,
                'invoice_id' => $payment->invoice_id,
                'invoice_payment_id' => $payment->id,
                'confirmed_by' => $user->id,
                'matched_by' => $index < 17 ? 'automatic' : 'manual',
                'score' => $hasBankFee ? 96 : 100,
                'applied_amount' => $payment->amount,
                'status' => Reconciliation::STATUS_CONFIRMED,
                'notes' => $hasBankFee ? 'Selisih biaya admin bank dikenali dan dicatat otomatis.' : 'Nominal dan referensi cocok.',
                'confirmed_at' => $payment->paid_at->addMinutes(3),
            ]);

            if ($hasBankFee) {
                $reconciliation->adjustments()->create([
                    'type' => 'bank_fee',
                    'amount' => 1_500,
                    'description' => 'Biaya admin transfer bank.',
                ]);
            }
        }
    }

    private function createReviewTransactions(User $user, BankAccount $account, BankTransactionImport $import): void
    {
        $smallInvoice = Invoice::query()
            ->where('user_id', $user->id)
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('balance_due', 450_000)
            ->firstOrFail();

        $candidate = BankTransaction::query()->create([
            'user_id' => $user->id,
            'bank_account_id' => $account->id,
            'bank_transaction_import_id' => $import->id,
            'external_transaction_id' => 'DEMO-TXN-NEEDS-001',
            'fingerprint' => hash('sha256', 'demo-needs-confirmation'),
            'type' => 'credit',
            'amount' => 448_500,
            'sender_name' => $smallInvoice->customer_name,
            'description' => 'Transfer dari '.$smallInvoice->customer_name,
            'reference' => 'TRF/BCA/8842',
            'transaction_at' => now()->subHours(3),
            'status' => BankTransaction::STATUS_NEEDS_CONFIRMATION,
            'raw_payload' => ['demo' => true],
        ]);
        ReconciliationSuggestion::query()->create([
            'bank_transaction_id' => $candidate->id,
            'invoice_id' => $smallInvoice->id,
            'score' => 96,
            'suggested_applied_amount' => 450_000,
            'difference_amount' => 1_500,
            'difference_type' => 'bank_fee',
            'reasons' => [
                'Nominal transfer sama dengan tagihan setelah dikurangi biaya admin Rp1.500.',
                'Nama pengirim sesuai dengan pelanggan pada invoice.',
                'Transfer diterima dalam periode pembayaran invoice.',
            ],
            'status' => 'pending',
            'ai_rank' => 1,
            'ai_confidence' => 96,
            'ai_reasons' => ['Pola pembayaran dan riwayat pelanggan mendukung kandidat ini.'],
            'ai_requires_review' => true,
        ]);

        foreach ([
            ['id' => 'DEMO-TXN-UNMATCHED-001', 'amount' => 1_240_000, 'sender' => 'SETTLEMENT COD TOKOPEDIA', 'description' => 'Pencairan transaksi COD belum diimpor'],
            ['id' => 'DEMO-TXN-UNMATCHED-002', 'amount' => 780_000, 'sender' => 'RIZKY PRATAMA', 'description' => 'Transfer tanpa nomor invoice'],
        ] as $index => $fixture) {
            BankTransaction::query()->create([
                'user_id' => $user->id,
                'bank_account_id' => $account->id,
                'bank_transaction_import_id' => $import->id,
                'external_transaction_id' => $fixture['id'],
                'fingerprint' => hash('sha256', strtolower($fixture['id'])),
                'type' => 'credit',
                'amount' => $fixture['amount'],
                'sender_name' => $fixture['sender'],
                'description' => $fixture['description'],
                'reference' => 'PENDING-'.($index + 1),
                'transaction_at' => now()->subDays($index + 1),
                'status' => BankTransaction::STATUS_UNMATCHED,
                'raw_payload' => ['demo' => true],
            ]);
        }

        BankTransaction::query()->create([
            'user_id' => $user->id,
            'bank_account_id' => $account->id,
            'bank_transaction_import_id' => $import->id,
            'external_transaction_id' => 'DEMO-TXN-FEE-001',
            'fingerprint' => hash('sha256', 'demo-marketplace-fee'),
            'type' => 'debit',
            'amount' => 78_000,
            'sender_name' => null,
            'description' => 'Potongan biaya layanan marketplace',
            'reference' => 'FEE-MKT-001',
            'transaction_at' => now()->subDay(),
            'status' => BankTransaction::STATUS_IGNORED,
            'raw_payload' => ['demo' => true],
        ]);
    }
}
