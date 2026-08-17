<?php

namespace App\Services\Report;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Models\WeeklyFinancialReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;

class WeeklyFinancialReportService
{
    public function generate(User $user, CarbonImmutable $start, CarbonImmutable $end): WeeklyFinancialReport
    {
        $rangeStart = $start->startOfDay();
        $rangeEnd = $end->endOfDay();
        $payments = InvoicePayment::query()->active()
            ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
            ->whereBetween('paid_at', [$rangeStart, $rangeEnd]);
        $issuedInvoices = Invoice::query()->where('user_id', $user->id)
            ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_VOID]);
        $outstanding = Invoice::query()->where('user_id', $user->id)
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('balance_due', '>', 0);

        $metrics = [
            'receivedAmount' => (int) (clone $payments)->sum('amount'),
            'paymentCount' => (clone $payments)->count(),
            'paidInvoiceCount' => (clone $payments)->distinct('invoice_id')->count('invoice_id'),
            'issuedInvoiceCount' => (clone $issuedInvoices)->count(),
            'issuedAmount' => (int) (clone $issuedInvoices)->sum('total_amount'),
            'outstandingInvoiceCount' => (clone $outstanding)->count(),
            'outstandingAmount' => (int) (clone $outstanding)->sum('balance_due'),
            'overdueInvoiceCount' => (clone $outstanding)->whereDate('due_date', '<', now()->toDateString())->count(),
            'newCustomerCount' => Customer::query()->where('user_id', $user->id)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])->count(),
        ];

        return WeeklyFinancialReport::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ],
            [
                'status' => WeeklyFinancialReport::STATUS_READY,
                'metrics' => $metrics,
                'generated_at' => now(),
            ],
        );
    }

    public function renderPdf(WeeklyFinancialReport $report): string
    {
        return Pdf::loadView('pdf.weekly-financial-report', [
            'report' => $report->loadMissing('owner'),
        ])->setPaper('a4')->output();
    }
}
