<?php

namespace App\Services\Reconciliation;

use App\Models\BankTransaction;
use App\Models\Reconciliation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ReconciliationQueryService
{
    /** @param array<string, mixed> $filters */
    public function transactions(User $user, array $filters): LengthAwarePaginator
    {
        return BankTransaction::query()
            ->with('bankAccount')
            ->where('user_id', $user->id)
            ->when($filters['bankAccountId'] ?? null, fn (Builder $query, int $id) => $query->where('bank_account_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['dateFrom'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_at', '>=', $date))
            ->when($filters['dateTo'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_at', '<=', $date))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('sender_name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('description', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('reference', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('external_transaction_id', "%{$search}%", caseSensitive: false);
                });
            })
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->paginate((int) $filters['perPage'], page: (int) $filters['page'])
            ->withQueryString();
    }

    /** @param array<string, mixed> $filters */
    public function reconciliations(User $user, array $filters): LengthAwarePaginator
    {
        return Reconciliation::query()
            ->with(['bankTransaction.bankAccount', 'invoice', 'adjustments'])
            ->where('user_id', $user->id)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['matchedBy'] ?? null, fn (Builder $query, string $matchedBy) => $query->where('matched_by', $matchedBy))
            ->when($filters['dateFrom'] ?? null, fn (Builder $query, string $date) => $query->whereDate('confirmed_at', '>=', $date))
            ->when($filters['dateTo'] ?? null, fn (Builder $query, string $date) => $query->whereDate('confirmed_at', '<=', $date))
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->paginate((int) $filters['perPage'], page: (int) $filters['page'])
            ->withQueryString();
    }

    /** @return array<string, int> */
    public function summary(User $user): array
    {
        $base = BankTransaction::query()->where('user_id', $user->id)->where('type', 'credit');
        $total = (clone $base)->count();
        $matched = (clone $base)->where('status', BankTransaction::STATUS_MATCHED)->count();

        return [
            'totalTransactions' => $total,
            'matchedTransactions' => $matched,
            'unmatchedTransactions' => (clone $base)->where('status', BankTransaction::STATUS_UNMATCHED)->count(),
            'needsConfirmation' => (clone $base)->where('status', BankTransaction::STATUS_NEEDS_CONFIRMATION)->count(),
            'ignoredTransactions' => (clone $base)->where('status', BankTransaction::STATUS_IGNORED)->count(),
            'matchPercentage' => $total === 0 ? 0 : (int) round(($matched / $total) * 100),
            'totalIncomingAmount' => (int) (clone $base)->sum('amount'),
        ];
    }
}
