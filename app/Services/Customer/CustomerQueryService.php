<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CustomerQueryService
{
    /** @param array<string, mixed> $filters */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->withMetrics(Customer::query()->where('user_id', $user->id))
            ->with('latestInvoice')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('customer_code', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('email', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('whatsapp', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('city', "%{$search}%", caseSensitive: false);
                });
            })
            ->when(($filters['status'] ?? 'all') !== 'all', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when(array_key_exists('hasOutstanding', $filters), function (Builder $query) use ($filters): void {
                $method = $filters['hasOutstanding'] ? 'whereHas' : 'whereDoesntHave';
                $query->{$method}('invoices', fn (Builder $query) => $query
                    ->where('status', Invoice::STATUS_UNPAID)->where('balance_due', '>', 0));
            });

        $this->applySort($query, $filters['sortBy'], $filters['sortDirection']);

        return $query->paginate((int) $filters['perPage'], page: (int) $filters['page'])->withQueryString();
    }

    public function detail(Customer $customer): Customer
    {
        return $this->withMetrics(Customer::query()->whereKey($customer->id))
            ->with('latestInvoice')->firstOrFail();
    }

    /** @return array<string, int|float|null> */
    public function summary(User $user, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month)->startOfMonth();
        $end = $start->endOfMonth();
        $issued = Invoice::query()->where('user_id', $user->id)
            ->whereNotNull('customer_id')
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID]);
        $periodInvoices = (clone $issued)->whereBetween('issue_date', [$start, $end]);
        $transactingCustomers = (clone $periodInvoices)->whereNotNull('customer_id')->distinct('customer_id')->count('customer_id');
        $periodValue = (int) (clone $periodInvoices)->sum('total_amount');

        return [
            'totalCustomers' => Customer::query()->where('user_id', $user->id)->count(),
            'activeCustomers' => Customer::query()->where('user_id', $user->id)->where('is_active', true)->count(),
            'newCustomersThisMonth' => Customer::query()->where('user_id', $user->id)->whereBetween('created_at', [$start, $end])->count(),
            'customersWithOutstanding' => Customer::query()->where('user_id', $user->id)
                ->whereHas('invoices', fn (Builder $query) => $query->where('status', Invoice::STATUS_UNPAID)->where('balance_due', '>', 0))->count(),
            'totalOutstanding' => (int) (clone $issued)->where('status', Invoice::STATUS_UNPAID)->sum('balance_due'),
            'averageMonthlyTransactionValue' => $transactingCustomers === 0 ? 0 : (int) round($periodValue / $transactingCustomers),
            'periodTransactionValue' => $periodValue,
        ];
    }

    private function withMetrics(Builder $query): Builder
    {
        return $query
            ->withCount([
                'invoices as total_invoice_count' => fn (Builder $query) => $query->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID]),
                'invoices as paid_invoice_count' => fn (Builder $query) => $query->where('status', Invoice::STATUS_PAID),
                'invoices as unpaid_invoice_count' => fn (Builder $query) => $query->where('status', Invoice::STATUS_UNPAID),
                'invoices as overdue_invoice_count' => fn (Builder $query) => $query->where('status', Invoice::STATUS_UNPAID)->whereDate('due_date', '<', today()),
            ])
            ->withSum(['invoices as total_transaction_value' => fn (Builder $query) => $query->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID])], 'total_amount')
            ->withSum(['invoices as total_paid' => fn (Builder $query) => $query->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID])], 'paid_amount')
            ->withSum(['invoices as total_outstanding' => fn (Builder $query) => $query->where('status', Invoice::STATUS_UNPAID)], 'balance_due')
            ->withMax(['invoices as last_invoice_date' => fn (Builder $query) => $query->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID])], 'issue_date');
    }

    private function applySort(Builder $query, string $sortBy, string $direction): void
    {
        $column = match ($sortBy) {
            'totalTransactionValue' => 'total_transaction_value',
            'totalOutstanding' => 'total_outstanding',
            'lastInvoiceDate' => 'last_invoice_date',
            'newest' => 'created_at',
            default => 'name',
        };
        $query->orderBy($column, $direction)->orderBy('id');
    }
}
