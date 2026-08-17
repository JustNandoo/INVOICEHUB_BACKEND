<?php

namespace App\Services\Reconciliation;

use App\Events\Invoice\InvoicePaid;
use App\Events\Invoice\InvoicePaymentRecorded;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Reconciliation;
use App\Models\ReconciliationSuggestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconciliationService
{
    /** @param array<string, mixed> $data */
    public function confirm(User $user, array $data): Reconciliation
    {
        return $this->performConfirm($user, $data, 'manual', $user->id);
    }

    public function confirmExactRule(User $user, BankTransaction $transaction, Invoice $invoice): Reconciliation
    {
        return $this->performConfirm($user, [
            'bankTransactionId' => $transaction->id,
            'invoiceId' => $invoice->id,
            'appliedAmount' => $transaction->amount,
            'score' => 100,
            'notes' => 'Dicocokkan otomatis: nomor invoice dan nominal sama persis.',
        ], 'exact_rule', null);
    }

    /** @param array<string, mixed> $data */
    private function performConfirm(User $user, array $data, string $matchedBy, ?int $confirmerId): Reconciliation
    {
        return DB::transaction(function () use ($user, $data, $matchedBy, $confirmerId): Reconciliation {
            $transaction = BankTransaction::query()
                ->where('user_id', $user->id)
                ->whereKey($data['bankTransactionId'])
                ->lockForUpdate()
                ->firstOrFail();
            $invoice = Invoice::query()
                ->where('user_id', $user->id)
                ->whereKey($data['invoiceId'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->guardCanConfirm($transaction, $invoice, $data);
            $adjustments = $data['adjustments'] ?? [];
            $adjustmentTotal = (int) collect($adjustments)->sum('amount');

            if ((int) $data['appliedAmount'] !== $transaction->amount + $adjustmentTotal) {
                throw ValidationException::withMessages([
                    'appliedAmount' => ['Nominal diterapkan harus sama dengan mutasi ditambah total penyesuaian.'],
                ]);
            }

            $suggestion = ReconciliationSuggestion::query()
                ->where('bank_transaction_id', $transaction->id)
                ->where('invoice_id', $invoice->id)
                ->first();
            $reconciliation = Reconciliation::query()->create([
                'user_id' => $user->id,
                'bank_transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
                'confirmed_by' => $confirmerId,
                'matched_by' => $matchedBy,
                'score' => $suggestion?->score ?? ($data['score'] ?? null),
                'applied_amount' => (int) $data['appliedAmount'],
                'status' => Reconciliation::STATUS_CONFIRMED,
                'notes' => $data['notes'] ?? null,
                'confirmed_at' => now(),
            ]);
            $payment = $invoice->payments()->create([
                'recorded_by' => $confirmerId,
                'amount' => (int) $data['appliedAmount'],
                'method' => 'bank_transfer',
                'source' => 'reconciliation',
                'reference' => $transaction->reference ?? $transaction->external_transaction_id,
                'paid_at' => $transaction->transaction_at,
                'notes' => 'Pembayaran dari rekonsiliasi mutasi #'.$transaction->id,
            ]);
            $reconciliation->update(['invoice_payment_id' => $payment->id]);
            $reconciliation->adjustments()->createMany($adjustments);

            $paidAmount = $invoice->paid_amount + (int) $data['appliedAmount'];
            $balanceDue = $invoice->total_amount - $paidAmount;
            $isPaid = $balanceDue === 0;
            $invoice->update([
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'status' => $isPaid ? Invoice::STATUS_PAID : Invoice::STATUS_UNPAID,
                'paid_at' => $isPaid ? $transaction->transaction_at : null,
            ]);
            $transaction->update(['status' => BankTransaction::STATUS_MATCHED]);
            $transaction->suggestions()->where('invoice_id', $invoice->id)->update(['status' => 'accepted']);
            $transaction->suggestions()->where('invoice_id', '!=', $invoice->id)->where('status', 'pending')->update(['status' => 'rejected']);
            $invoice->activities()->create([
                'actor_id' => $confirmerId,
                'type' => $isPaid ? 'paid' : 'payment_received',
                'title' => $isPaid ? 'Pembayaran Dicocokkan (Lunas)' : 'Pembayaran Sebagian Dicocokkan',
                'description' => 'Mutasi '.$transaction->bankAccount->bank_code.' sebesar Rp '.number_format($transaction->amount, 0, ',', '.').' berhasil direkonsiliasi.',
                'metadata' => ['reconciliationId' => $reconciliation->id, 'bankTransactionId' => $transaction->id],
                'occurred_at' => now(),
            ]);
            InvoicePaymentRecorded::dispatch($invoice->id, $payment->id);
            if ($isPaid) {
                InvoicePaid::dispatch($invoice->id, $payment->id);
            }

            return $this->load($reconciliation->refresh());
        }, 3);
    }

    public function reverse(Reconciliation $reconciliation, User $user, string $reason): Reconciliation
    {
        return DB::transaction(function () use ($reconciliation, $user, $reason): Reconciliation {
            $reconciliation = Reconciliation::query()->whereKey($reconciliation->id)->lockForUpdate()->firstOrFail();
            if ($reconciliation->status !== Reconciliation::STATUS_CONFIRMED) {
                throw ValidationException::withMessages(['reconciliation' => ['Rekonsiliasi sudah dibatalkan.']]);
            }
            $transaction = BankTransaction::query()->whereKey($reconciliation->bank_transaction_id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::query()->whereKey($reconciliation->invoice_id)->lockForUpdate()->firstOrFail();
            $payment = InvoicePayment::query()->whereKey($reconciliation->invoice_payment_id)->lockForUpdate()->firstOrFail();

            $payment->update(['voided_at' => now(), 'void_reason' => $reason]);
            $paidAmount = max(0, $invoice->paid_amount - $payment->amount);
            $invoice->update([
                'paid_amount' => $paidAmount,
                'balance_due' => $invoice->total_amount - $paidAmount,
                'status' => Invoice::STATUS_UNPAID,
                'paid_at' => null,
            ]);
            $transaction->update(['status' => BankTransaction::STATUS_UNMATCHED]);
            $transaction->suggestions()->where('invoice_id', $invoice->id)->update(['status' => 'pending']);
            $reconciliation->update([
                'status' => Reconciliation::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);
            $invoice->activities()->create([
                'actor_id' => $user->id,
                'type' => 'reconciliation_reversed',
                'title' => 'Rekonsiliasi Dibatalkan',
                'description' => $reason,
                'metadata' => ['reconciliationId' => $reconciliation->id],
                'occurred_at' => now(),
            ]);

            return $this->load($reconciliation->refresh());
        }, 3);
    }

    public function ignore(BankTransaction $transaction, ?string $reason): BankTransaction
    {
        if ($transaction->status === BankTransaction::STATUS_MATCHED) {
            throw ValidationException::withMessages(['transaction' => ['Mutasi yang sudah cocok harus dibatalkan rekonsiliasinya terlebih dahulu.']]);
        }
        $rawPayload = $transaction->raw_payload ?? [];
        $rawPayload['ignoredReason'] = $reason;
        $transaction->update(['status' => BankTransaction::STATUS_IGNORED, 'raw_payload' => $rawPayload]);

        return $transaction->refresh()->load('bankAccount');
    }

    public function rejectCandidate(BankTransaction $transaction, int $invoiceId, ?string $reason): ReconciliationSuggestion
    {
        if (in_array($transaction->status, [BankTransaction::STATUS_MATCHED, BankTransaction::STATUS_IGNORED], true)) {
            throw ValidationException::withMessages(['transaction' => ['Kandidat pada mutasi ini tidak dapat ditolak.']]);
        }
        $suggestion = $transaction->suggestions()->where('invoice_id', $invoiceId)->firstOrFail();
        $reasons = $suggestion->reasons;
        if ($reason !== null) {
            $reasons[] = 'Ditolak pengguna: '.$reason;
        }
        $suggestion->update(['status' => 'rejected', 'reasons' => $reasons]);
        if (! $transaction->suggestions()->where('status', 'pending')->exists()) {
            $transaction->update(['status' => BankTransaction::STATUS_UNMATCHED]);
        }

        return $suggestion->refresh()->load('invoice');
    }

    public function load(Reconciliation $reconciliation): Reconciliation
    {
        return $reconciliation->load(['bankTransaction.bankAccount', 'invoice', 'adjustments']);
    }

    /** @param array<string, mixed> $data */
    private function guardCanConfirm(BankTransaction $transaction, Invoice $invoice, array $data): void
    {
        if ($transaction->type !== 'credit' || in_array($transaction->status, [BankTransaction::STATUS_MATCHED, BankTransaction::STATUS_IGNORED], true)) {
            throw ValidationException::withMessages(['bankTransactionId' => ['Mutasi tidak tersedia untuk rekonsiliasi.']]);
        }
        if (Reconciliation::query()->where('bank_transaction_id', $transaction->id)->where('status', Reconciliation::STATUS_CONFIRMED)->exists()) {
            throw ValidationException::withMessages(['bankTransactionId' => ['Mutasi sudah memiliki rekonsiliasi aktif.']]);
        }
        if ($invoice->status !== Invoice::STATUS_UNPAID || $invoice->balance_due <= 0) {
            throw ValidationException::withMessages(['invoiceId' => ['Invoice tidak memiliki sisa tagihan yang dapat dibayar.']]);
        }
        if ((int) $data['appliedAmount'] > $invoice->balance_due) {
            throw ValidationException::withMessages(['appliedAmount' => ['Nominal melebihi sisa tagihan invoice.']]);
        }
    }
}
