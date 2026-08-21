<?php

namespace App\Listeners\Ai;

use App\Events\Invoice\InvoicePaymentRecorded;
use App\Models\AiFeedback;
use App\Models\Reconciliation;
use App\Models\ReconciliationSuggestion;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Turns every human confirmation into a labelled training signal without touching the
 * financial write path: reconciliation itself stays entirely inside ReconciliationService.
 */
class RecordReconciliationAiFeedback implements ShouldQueueAfterCommit
{
    public string $queue = 'ai';

    public int $tries = 3;

    public function handle(InvoicePaymentRecorded $event): void
    {
        $reconciliation = Reconciliation::query()
            ->where('invoice_payment_id', $event->paymentId)
            ->first();

        if (! $reconciliation) {
            return;
        }

        $recommended = ReconciliationSuggestion::query()
            ->where('bank_transaction_id', $reconciliation->bank_transaction_id)
            ->whereNotNull('ai_run_id')
            ->where('ai_rank', 1)
            ->first();

        if (! $recommended) {
            return;
        }

        $accepted = (int) $recommended->invoice_id === (int) $reconciliation->invoice_id;

        AiFeedback::query()->updateOrCreate(
            ['ai_run_id' => $recommended->ai_run_id, 'user_id' => $reconciliation->user_id],
            [
                'accepted' => $accepted,
                'corrected_value' => $accepted ? null : ['invoiceId' => (int) $reconciliation->invoice_id],
            ],
        );
    }
}
