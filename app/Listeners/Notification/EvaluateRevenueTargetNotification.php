<?php

namespace App\Listeners\Notification;

use App\Events\Invoice\InvoicePaymentRecorded;
use App\Events\Report\RevenueTargetReached;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\Notification\RevenueTargetService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class EvaluateRevenueTargetNotification implements ShouldQueueAfterCommit
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public function __construct(
        private readonly RevenueTargetService $targets,
    ) {}

    public function handle(InvoicePaymentRecorded $event): void
    {
        $invoice = Invoice::query()->with('owner')->find($event->invoiceId);
        $payment = InvoicePayment::query()->find($event->paymentId);

        if (! $invoice || ! $payment) {
            return;
        }

        $paidAt = $payment->paid_at->setTimezone(config('notifications.timezone'));
        $result = $this->targets->evaluate($invoice->owner, $paidAt->year, $paidAt->month);
        if (! $result['newlyReached'] || ! $result['target']) {
            return;
        }

        $target = $result['target'];
        RevenueTargetReached::dispatch($target->id, $result['currentRevenue']);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
