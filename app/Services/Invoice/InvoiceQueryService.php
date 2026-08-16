<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class InvoiceQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $query = Invoice::query()->where('user_id', $user->id);
        $status = $filters['status'] ?? null;

        if ($status === null) {
            $query->where('status', '!=', Invoice::STATUS_VOID);
        } elseif ($status === 'overdue') {
            $query->where('status', Invoice::STATUS_UNPAID)->whereDate('due_date', '<', today());
        } elseif ($status === Invoice::STATUS_UNPAID) {
            $query->where('status', Invoice::STATUS_UNPAID)->whereDate('due_date', '>=', today());
        } else {
            $query->where('status', $status);
        }

        $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('number', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('customer_name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('customer_email', "%{$search}%", caseSensitive: false);
                });
            })
            ->when($filters['issueFrom'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($filters['issueTo'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issue_date', '<=', $date))
            ->when($filters['dueFrom'] ?? null, fn (Builder $query, string $date) => $query->whereDate('due_date', '>=', $date))
            ->when($filters['dueTo'] ?? null, fn (Builder $query, string $date) => $query->whereDate('due_date', '<=', $date));

        $this->applySort($query, $filters['sort'] ?? 'newest');

        return $query->paginate(
            perPage: (int) $filters['perPage'],
            page: (int) $filters['page'],
        )->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    public function summary(User $user): array
    {
        $issued = Invoice::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID]);
        $unpaid = (clone $issued)->where('status', Invoice::STATUS_UNPAID);
        $overdue = (clone $unpaid)->whereDate('due_date', '<', today());

        return [
            'totalInvoices' => (clone $issued)->count(),
            'totalBilled' => (int) (clone $issued)->sum('total_amount'),
            'paidInvoices' => (clone $issued)->where('status', Invoice::STATUS_PAID)->count(),
            'unpaidInvoices' => (clone $unpaid)->count(),
            'unpaidAmount' => (int) (clone $unpaid)->sum('balance_due'),
            'overdueInvoices' => (clone $overdue)->count(),
            'overdueAmount' => (int) (clone $overdue)->sum('balance_due'),
            'draftInvoices' => Invoice::query()->where('user_id', $user->id)->where('status', Invoice::STATUS_DRAFT)->count(),
        ];
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('issue_date')->orderBy('id'),
            'dueSoon' => $query->orderBy('due_date')->orderBy('id'),
            'amountHigh' => $query->orderByDesc('total_amount')->orderByDesc('id'),
            'amountLow' => $query->orderBy('total_amount')->orderBy('id'),
            default => $query->orderByDesc('issue_date')->orderByDesc('id'),
        };
    }
}
