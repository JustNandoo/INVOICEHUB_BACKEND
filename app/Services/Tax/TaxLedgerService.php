<?php

namespace App\Services\Tax;

use App\Models\BankTransaction;
use App\Models\InvoicePayment;
use App\Models\TaxAuditFinding;
use App\Models\TaxLedgerEntry;
use App\Models\User;

class TaxLedgerService
{
    public function syncInvoicePayments(User $user, int $year): void
    {
        InvoicePayment::query()
            ->with('invoice:id,user_id,number,customer_name')
            ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
            ->whereYear('paid_at', $year)
            ->each(function (InvoicePayment $payment) use ($user): void {
                TaxLedgerEntry::query()->updateOrCreate(
                    ['user_id' => $user->id, 'source_type' => 'invoice_payment', 'source_id' => $payment->id],
                    [
                        'entry_type' => 'revenue',
                        'amount' => $payment->amount,
                        'status' => $payment->voided_at ? TaxLedgerEntry::STATUS_EXCLUDED : TaxLedgerEntry::STATUS_RECORDED,
                        'is_taxable' => true,
                        'recognized_at' => $payment->paid_at,
                        'description' => 'Payment for '.$payment->invoice->number,
                        'metadata' => ['invoiceId' => $payment->invoice_id, 'invoiceNumber' => $payment->invoice->number],
                    ],
                );
            });
    }

    public function includeFinding(User $user, TaxAuditFinding $finding): TaxLedgerEntry
    {
        if ($finding->source_type !== 'bank_transaction' || ! $finding->source_id) {
            abort(422, 'This finding cannot be added as tax revenue.');
        }

        $transaction = BankTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'credit')
            ->findOrFail($finding->source_id);

        $entry = TaxLedgerEntry::query()->updateOrCreate(
            ['user_id' => $user->id, 'source_type' => 'bank_transaction', 'source_id' => $transaction->id],
            [
                'entry_type' => 'revenue', 'amount' => $transaction->amount,
                'status' => TaxLedgerEntry::STATUS_RECORDED, 'is_taxable' => true,
                'recognized_at' => $transaction->transaction_at,
                'description' => $transaction->description ?: 'Bank revenue',
                'metadata' => ['reference' => $transaction->reference, 'senderName' => $transaction->sender_name],
            ],
        );

        $finding->update([
            'status' => TaxAuditFinding::STATUS_RESOLVED, 'resolution' => 'included_as_revenue',
            'notes' => 'Added to the tax ledger.', 'resolved_by' => $user->id, 'resolved_at' => now(),
        ]);

        return $entry;
    }
}
