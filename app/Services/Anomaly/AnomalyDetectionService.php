<?php

namespace App\Services\Anomaly;

use App\Enums\Ai\InsightSeverity;
use App\Enums\Anomaly\AnomalyStatus;
use App\Enums\Anomaly\AnomalyType;
use App\Models\BankTransaction;
use App\Models\FinancialAnomaly;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Reconciliation;
use App\Models\ReconciliationAdjustment;
use App\Models\User;
use App\Services\Subscription\EntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic leak detection. No AI is involved here at all: every finding is produced
 * by an explicit rule over the user's own records, so the numbers are always defensible.
 * AI only comes later, to explain a finding the user chose to open.
 */
class AnomalyDetectionService
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    /**
     * Re-scan a user and reconcile the stored findings with reality.
     *
     * @return array{detected: int, resolved: int, open: int, amountAtRisk: int}
     */
    public function scan(User $user): array
    {
        $this->entitlements->require($user, 'anomaly.detection');

        $since = CarbonImmutable::now()->subDays((int) config('anomaly.lookback_days', 90));
        $found = collect();

        foreach (AnomalyType::cases() as $type) {
            if (! ($type->config()['enabled'] ?? false)) {
                continue;
            }

            $found = $found->concat($this->detect($type, $user, $since));
        }

        return DB::transaction(function () use ($user, $found): array {
            $detected = 0;

            foreach ($found as $finding) {
                $detected += $this->store($user, $finding) ? 1 : 0;
            }

            $resolved = $this->autoResolveDisappeared($user, $found);
            $open = FinancialAnomaly::query()->where('user_id', $user->id)->open();

            return [
                'detected' => $detected,
                'resolved' => $resolved,
                'open' => (clone $open)->count(),
                'amountAtRisk' => (int) (clone $open)->sum('amount_at_risk'),
            ];
        }, 3);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function detect(AnomalyType $type, User $user, CarbonImmutable $since): Collection
    {
        return match ($type) {
            AnomalyType::UnmatchedIncoming => $this->unmatchedIncoming($user, $since),
            AnomalyType::ExcessiveFee => $this->excessiveFee($user, $since),
            AnomalyType::ExcessiveDiscount => $this->excessiveDiscount($user, $since),
            AnomalyType::UnsettledBalance => $this->unsettledBalance($user, $since),
            AnomalyType::DuplicatePayment => $this->duplicatePayment($user, $since),
            AnomalyType::RepeatedReversal => $this->repeatedReversal($user, $since),
        };
    }

    /** Money arrived but was never tied to an invoice. @return Collection<int, array<string, mixed>> */
    private function unmatchedIncoming(User $user, CarbonImmutable $since): Collection
    {
        $rule = AnomalyType::UnmatchedIncoming->config();

        return BankTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'credit')
            ->whereIn('status', [BankTransaction::STATUS_UNMATCHED, BankTransaction::STATUS_NEEDS_CONFIRMATION])
            ->where('amount', '>=', (int) $rule['min_amount'])
            ->whereBetween('transaction_at', [$since, now()->subDays((int) $rule['min_age_days'])])
            ->get()
            ->map(fn (BankTransaction $transaction): array => $this->finding(
                AnomalyType::UnmatchedIncoming,
                'bank_transaction',
                $transaction->id,
                sprintf('Transfer masuk %s belum tercocokkan', $this->rupiah($transaction->amount)),
                sprintf(
                    'Uang masuk sejak %s belum dikaitkan ke invoice mana pun, sehingga pendapatan ini belum tercatat pada tagihan.',
                    $transaction->transaction_at->format('d/m/Y'),
                ),
                (int) $transaction->amount,
                [
                    'transactionAt' => $transaction->transaction_at->toIso8601String(),
                    'senderName' => $transaction->sender_name,
                    'ageDays' => (int) $transaction->transaction_at->diffInDays(now()),
                ],
            ));
    }

    /** A bank or marketplace fee well above what is normal. @return Collection<int, array<string, mixed>> */
    private function excessiveFee(User $user, CarbonImmutable $since): Collection
    {
        $rule = AnomalyType::ExcessiveFee->config();

        return ReconciliationAdjustment::query()
            ->whereIn('type', ['bank_fee', 'marketplace_fee'])
            ->whereHas('reconciliation', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', Reconciliation::STATUS_CONFIRMED)
                ->where('confirmed_at', '>=', $since))
            ->with('reconciliation')
            ->get()
            ->filter(function (ReconciliationAdjustment $adjustment) use ($rule): bool {
                $applied = (int) ($adjustment->reconciliation?->applied_amount ?? 0);
                $percent = $applied > 0 ? ($adjustment->amount / $applied) * 100 : 0;

                return $adjustment->amount > (int) $rule['max_amount'] || $percent > (float) $rule['max_percent'];
            })
            ->map(function (ReconciliationAdjustment $adjustment): array {
                $applied = (int) ($adjustment->reconciliation?->applied_amount ?? 0);
                $percent = $applied > 0 ? round(($adjustment->amount / $applied) * 100, 1) : 0;

                return $this->finding(
                    AnomalyType::ExcessiveFee,
                    'reconciliation',
                    (int) $adjustment->reconciliation_id,
                    sprintf('Potongan %s terasa tinggi', $this->rupiah($adjustment->amount)),
                    sprintf(
                        'Potongan %s setara %s%% dari nilai pembayaran. Nilai ini di atas kebiasaan dan perlu dicek ke pihak bank atau marketplace.',
                        $adjustment->type === 'marketplace_fee' ? 'marketplace' : 'bank',
                        $percent,
                    ),
                    (int) $adjustment->amount,
                    ['feeType' => $adjustment->type, 'appliedAmount' => $applied, 'percentOfPayment' => $percent],
                );
            });
    }

    /** Discount far beyond the usual promo range. @return Collection<int, array<string, mixed>> */
    private function excessiveDiscount(User $user, CarbonImmutable $since): Collection
    {
        $rule = AnomalyType::ExcessiveDiscount->config();

        return Invoice::query()
            ->where('user_id', $user->id)
            ->where('discount_amount', '>=', (int) $rule['min_amount'])
            ->where('subtotal', '>', 0)
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_VOID])
            ->where('issue_date', '>=', $since->toDateString())
            ->get()
            ->filter(fn (Invoice $invoice): bool => ($invoice->discount_amount / $invoice->subtotal) * 100 > (float) $rule['max_percent'])
            ->map(function (Invoice $invoice): array {
                $percent = round(($invoice->discount_amount / $invoice->subtotal) * 100, 1);

                return $this->finding(
                    AnomalyType::ExcessiveDiscount,
                    'invoice',
                    $invoice->id,
                    sprintf('Diskon %s%% pada %s', $percent, $invoice->number),
                    sprintf(
                        'Invoice ini memberi diskon %s dari subtotal %s. Persentasenya di atas ambang wajar yang Anda tetapkan.',
                        $this->rupiah($invoice->discount_amount),
                        $this->rupiah($invoice->subtotal),
                    ),
                    (int) $invoice->discount_amount,
                    ['invoiceNumber' => $invoice->number, 'discountPercent' => $percent, 'subtotal' => (int) $invoice->subtotal],
                );
            });
    }

    /** Reconciled, but a remainder was left behind and forgotten. @return Collection<int, array<string, mixed>> */
    private function unsettledBalance(User $user, CarbonImmutable $since): Collection
    {
        $rule = AnomalyType::UnsettledBalance->config();

        return Reconciliation::query()
            ->where('user_id', $user->id)
            ->where('status', Reconciliation::STATUS_CONFIRMED)
            ->whereBetween('confirmed_at', [$since, now()->subDays((int) $rule['min_age_days'])])
            ->with('invoice')
            ->get()
            ->filter(fn (Reconciliation $reconciliation): bool => $reconciliation->invoice !== null
                && $reconciliation->invoice->status === Invoice::STATUS_UNPAID
                && $reconciliation->invoice->balance_due >= (int) $rule['min_amount'])
            ->unique(fn (Reconciliation $reconciliation): int => (int) $reconciliation->invoice_id)
            ->map(fn (Reconciliation $reconciliation): array => $this->finding(
                AnomalyType::UnsettledBalance,
                'invoice',
                (int) $reconciliation->invoice_id,
                sprintf('Sisa tagihan %s pada %s', $this->rupiah($reconciliation->invoice->balance_due), $reconciliation->invoice->number),
                sprintf(
                    'Pembayaran sebagian sudah dicocokkan pada %s, tetapi sisa tagihan belum ditagihkan kembali sejak saat itu.',
                    $reconciliation->confirmed_at->format('d/m/Y'),
                ),
                (int) $reconciliation->invoice->balance_due,
                [
                    'invoiceNumber' => $reconciliation->invoice->number,
                    'reconciledAt' => $reconciliation->confirmed_at->toIso8601String(),
                    'paidAmount' => (int) $reconciliation->invoice->paid_amount,
                ],
            ));
    }

    /** Two identical payments on one invoice inside a short window. @return Collection<int, array<string, mixed>> */
    private function duplicatePayment(User $user, CarbonImmutable $since): Collection
    {
        $rule = AnomalyType::DuplicatePayment->config();

        return InvoicePayment::query()->active()
            ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
            ->where('paid_at', '>=', $since)
            ->with('invoice')
            ->get()
            ->groupBy(fn (InvoicePayment $payment): string => $payment->invoice_id.':'.$payment->amount)
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->filter(function (Collection $group) use ($rule): bool {
                $dates = $group->pluck('paid_at')->sort();

                return $dates->first()->diffInHours($dates->last(), absolute: true) <= (int) $rule['window_hours'];
            })
            ->map(function (Collection $group): array {
                /** @var InvoicePayment $first */
                $first = $group->first();
                $duplicates = $group->count() - 1;

                return $this->finding(
                    AnomalyType::DuplicatePayment,
                    'invoice',
                    (int) $first->invoice_id,
                    sprintf('%d pembayaran kembar pada %s', $duplicates, $first->invoice?->number ?? 'invoice'),
                    sprintf(
                        'Ada %d pembayaran bernilai sama (%s) tercatat berdekatan pada invoice ini. Bisa jadi satu transaksi terekam dua kali.',
                        $group->count(),
                        $this->rupiah((int) $first->amount),
                    ),
                    (int) $first->amount * $duplicates,
                    [
                        'invoiceNumber' => $first->invoice?->number,
                        'paymentCount' => $group->count(),
                        'paymentIds' => $group->pluck('id')->all(),
                    ],
                );
            })
            ->values();
    }

    /** The same transaction matched and unmatched over and over. @return Collection<int, array<string, mixed>> */
    private function repeatedReversal(User $user, CarbonImmutable $since): Collection
    {
        $rule = AnomalyType::RepeatedReversal->config();

        return Reconciliation::query()
            ->where('user_id', $user->id)
            ->where('status', Reconciliation::STATUS_REVERSED)
            ->where('reversed_at', '>=', $since)
            ->get()
            ->groupBy('bank_transaction_id')
            ->filter(fn (Collection $group): bool => $group->count() >= (int) $rule['min_count'])
            ->map(fn (Collection $group, $transactionId): array => $this->finding(
                AnomalyType::RepeatedReversal,
                'bank_transaction',
                (int) $transactionId,
                sprintf('Mutasi dibatalkan %d kali', $group->count()),
                sprintf(
                    'Mutasi ini sudah %d kali dicocokkan lalu dibatalkan. Pola seperti ini biasanya menandakan pencocokan yang keliru berulang.',
                    $group->count(),
                ),
                (int) $group->max('applied_amount'),
                ['reversalCount' => $group->count()],
            ))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function finding(
        AnomalyType $type,
        string $sourceType,
        int $sourceId,
        string $title,
        string $description,
        int $amountAtRisk,
        array $metadata = [],
    ): array {
        return [
            'type' => $type->value,
            'severity' => (string) ($type->config()['severity'] ?? InsightSeverity::Warning->value),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'title' => mb_substr($title, 0, 150),
            'description' => $description,
            'amount_at_risk' => max(0, $amountAtRisk),
            'metadata' => $metadata,
        ];
    }

    /** @param array<string, mixed> $finding */
    private function store(User $user, array $finding): bool
    {
        $existing = FinancialAnomaly::query()->where([
            'user_id' => $user->id,
            'type' => $finding['type'],
            'source_type' => $finding['source_type'],
            'source_id' => $finding['source_id'],
        ])->first();

        if ($existing !== null) {
            // Refresh the wording and amount, but never reopen something the user closed.
            $existing->update([
                'severity' => $finding['severity'],
                'title' => $finding['title'],
                'description' => $finding['description'],
                'amount_at_risk' => $finding['amount_at_risk'],
                'metadata' => $finding['metadata'],
            ]);

            return false;
        }

        FinancialAnomaly::query()->create([
            ...$finding,
            'user_id' => $user->id,
            'status' => AnomalyStatus::Open->value,
            'detected_at' => now(),
        ]);

        return true;
    }

    /**
     * A finding that no longer reproduces has been fixed in the underlying data, so it is
     * closed automatically rather than nagging the user forever.
     *
     * @param  Collection<int, array<string, mixed>>  $found
     */
    private function autoResolveDisappeared(User $user, Collection $found): int
    {
        $stillPresent = $found
            ->map(fn (array $finding): string => $finding['type'].':'.$finding['source_type'].':'.$finding['source_id'])
            ->all();

        $stale = FinancialAnomaly::query()
            ->where('user_id', $user->id)
            ->open()
            ->get()
            ->reject(fn (FinancialAnomaly $anomaly): bool => in_array(
                $anomaly->type->value.':'.$anomaly->source_type.':'.$anomaly->source_id,
                $stillPresent,
                true,
            ));

        foreach ($stale as $anomaly) {
            $anomaly->update([
                'status' => AnomalyStatus::Resolved->value,
                'resolution' => 'auto_resolved',
                'resolved_at' => now(),
            ]);
        }

        return $stale->count();
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
