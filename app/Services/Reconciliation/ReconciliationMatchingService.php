<?php

namespace App\Services\Reconciliation;

use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\ReconciliationSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReconciliationMatchingService
{
    /** @return Collection<int, ReconciliationSuggestion> */
    public function candidates(BankTransaction $transaction): Collection
    {
        if ($transaction->type !== 'credit' || in_array($transaction->status, [BankTransaction::STATUS_MATCHED, BankTransaction::STATUS_IGNORED], true)) {
            throw ValidationException::withMessages(['transaction' => ['Mutasi ini tidak dapat dicocokkan.']]);
        }

        $rejectedInvoiceIds = $transaction->suggestions()->where('status', 'rejected')->pluck('invoice_id');
        $invoices = Invoice::query()
            ->where('user_id', $transaction->user_id)
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('balance_due', '>', 0)
            ->whereDate('issue_date', '<=', $transaction->transaction_at->addDay())
            ->whereDate('due_date', '>=', $transaction->transaction_at->subDays(180))
            ->whereNotIn('id', $rejectedInvoiceIds)
            ->limit(200)
            ->get();

        $candidateIds = [];
        foreach ($invoices as $invoice) {
            $match = $this->score($transaction, $invoice);
            if ($match['score'] < 40) {
                continue;
            }
            $suggestion = $transaction->suggestions()->updateOrCreate(
                ['invoice_id' => $invoice->id],
                [...$match, 'status' => 'pending'],
            );
            $candidateIds[] = $suggestion->id;
        }

        if ($candidateIds !== [] && $transaction->status === BankTransaction::STATUS_UNMATCHED) {
            $transaction->update(['status' => BankTransaction::STATUS_NEEDS_CONFIRMATION]);
        }

        return ReconciliationSuggestion::query()
            ->with('invoice')
            ->whereIn('id', $candidateIds)
            ->orderByDesc('score')
            ->orderBy('difference_amount')
            ->limit(5)
            ->get();
    }

    /** @return array<string, mixed> */
    private function score(BankTransaction $transaction, Invoice $invoice): array
    {
        $score = 0;
        $reasons = [];
        $haystack = Str::lower(trim(($transaction->description ?? '').' '.($transaction->reference ?? '')));
        $invoiceNumber = Str::lower($invoice->number);
        $difference = abs($invoice->balance_due - $transaction->amount);
        $differenceType = null;

        if ($haystack !== '' && str_contains($haystack, $invoiceNumber)) {
            $score += 70;
            $reasons[] = 'Nomor invoice ditemukan pada deskripsi atau referensi transfer.';
        }

        if ($difference === 0) {
            $score += 60;
            $reasons[] = 'Nominal transfer sama persis dengan sisa tagihan.';
        } elseif ($difference <= min(5000, max(1000, (int) round($invoice->balance_due * 0.02)))) {
            $score += 45;
            $differenceType = $invoice->balance_due > $transaction->amount ? 'bank_fee' : 'overpayment';
            $reasons[] = 'Selisih nominal masih dalam batas biaya admin atau pembulatan.';
        }

        if ($this->senderMatchesCustomer($transaction->sender_name, $invoice->customer_name)) {
            $score += 25;
            $reasons[] = 'Nama pengirim sesuai dengan nama pelanggan.';
        }

        if ($transaction->transaction_at->between($invoice->issue_date->subDay(), $invoice->due_date->addDays(30))) {
            $score += 10;
            $reasons[] = 'Waktu transfer berada dalam periode pembayaran invoice.';
        }

        return [
            'score' => min(100, $score),
            'suggested_applied_amount' => $differenceType === 'bank_fee'
                ? $invoice->balance_due
                : min($invoice->balance_due, $transaction->amount),
            'difference_amount' => $difference,
            'difference_type' => $differenceType,
            'reasons' => $reasons,
        ];
    }

    private function senderMatchesCustomer(?string $sender, string $customer): bool
    {
        if ($sender === null) {
            return false;
        }
        $sender = Str::lower(preg_replace('/[^a-zA-Z0-9 ]/', ' ', $sender) ?? '');
        $customerWords = collect(explode(' ', Str::lower(preg_replace('/[^a-zA-Z0-9 ]/', ' ', $customer) ?? '')))
            ->filter(fn (string $word): bool => strlen($word) >= 4);

        return $customerWords->contains(fn (string $word): bool => str_contains($sender, $word));
    }
}
