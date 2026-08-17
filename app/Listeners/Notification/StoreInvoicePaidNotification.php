<?php

namespace App\Listeners\Notification;

use App\Enums\Notification\BusinessNotificationType;
use App\Enums\Notification\NotificationIcon;
use App\Enums\Notification\NotificationTone;
use App\Events\Invoice\InvoicePaid;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class StoreInvoicePaidNotification implements ShouldQueueAfterCommit
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(InvoicePaid $event): void
    {
        $invoice = Invoice::query()->with('owner')->find($event->invoiceId);
        $payment = InvoicePayment::query()->find($event->paymentId);

        if (! $invoice || ! $payment || $invoice->status !== Invoice::STATUS_PAID) {
            return;
        }

        $this->notifications->createOnce(
            $invoice->owner,
            BusinessNotificationType::InvoicePaid,
            "invoice-paid:{$invoice->id}",
            "Invoice #{$invoice->number} Lunas",
            $invoice->customer_name.' telah melakukan pembayaran sebesar Rp '.number_format($payment->amount, 0, ',', '.').'.',
            NotificationTone::Blue,
            NotificationIcon::Receipt,
            "/invoices/{$invoice->id}",
            ['invoiceId' => $invoice->id, 'paymentId' => $payment->id, 'amount' => $payment->amount],
        );
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
