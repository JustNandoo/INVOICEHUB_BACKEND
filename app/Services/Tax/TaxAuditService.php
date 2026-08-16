<?php

namespace App\Services\Tax;

use App\Models\BankTransaction;
use App\Models\TaxAuditFinding;
use App\Models\TaxLedgerEntry;
use App\Models\TaxPeriodReport;
use App\Models\User;
use Carbon\CarbonImmutable;

class TaxAuditService
{
    public function refresh(User $user, TaxPeriodReport $report): int
    {
        [$start, $end] = $this->range($report->year, $report->month);

        $transactions = BankTransaction::query()
            ->where('user_id', $user->id)->where('type', 'credit')
            ->whereIn('status', [BankTransaction::STATUS_UNMATCHED, BankTransaction::STATUS_NEEDS_CONFIRMATION])
            ->whereBetween('transaction_at', [$start, $end])->get();

        foreach ($transactions as $transaction) {
            $alreadyRecorded = TaxLedgerEntry::query()->where([
                'user_id' => $user->id, 'source_type' => 'bank_transaction', 'source_id' => $transaction->id,
            ])->where('status', TaxLedgerEntry::STATUS_RECORDED)->exists();

            if ($alreadyRecorded) {
                continue;
            }

            TaxAuditFinding::query()->firstOrCreate(
                [
                    'user_id' => $user->id, 'year' => $report->year, 'month' => $report->month,
                    'type' => 'unmatched_bank_transaction', 'source_type' => 'bank_transaction', 'source_id' => $transaction->id,
                ],
                [
                    'tax_period_report_id' => $report->id, 'severity' => 'warning',
                    'title' => 'Unmatched incoming bank transaction',
                    'description' => 'An incoming bank transaction has not been linked to an invoice or reviewed for tax reporting.',
                    'amount' => $transaction->amount, 'status' => TaxAuditFinding::STATUS_OPEN,
                    'recommended_action' => 'review_or_include',
                    'metadata' => ['reference' => $transaction->reference, 'senderName' => $transaction->sender_name],
                ],
            );
        }

        return TaxAuditFinding::query()->where('user_id', $user->id)
            ->where('year', $report->year)->where('month', $report->month)
            ->where('status', TaxAuditFinding::STATUS_OPEN)->count();
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function range(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month)->startOfMonth();

        return [$start, $start->endOfMonth()];
    }
}
