<?php

namespace App\Jobs\Notification;

use App\Enums\Notification\BusinessNotificationType;
use App\Enums\Notification\NotificationIcon;
use App\Enums\Notification\NotificationTone;
use App\Models\Invoice;
use App\Services\Notification\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateUpcomingInvoiceDueNotifications implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notifications): void
    {
        $today = CarbonImmutable::now(config('notifications.timezone'))->startOfDay();
        $lastDate = $today->addDays(config('notifications.invoice_due_days'));

        Invoice::query()->with('owner')
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('balance_due', '>', 0)
            ->whereDate('due_date', '>=', $today->toDateString())
            ->whereDate('due_date', '<=', $lastDate->toDateString())
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use ($notifications, $today): void {
                foreach ($invoices as $invoice) {
                    $days = (int) $today->diffInDays($invoice->due_date, false);
                    $deadline = match ($days) {
                        0 => 'jatuh tempo hari ini',
                        1 => 'jatuh tempo besok',
                        default => "jatuh tempo dalam {$days} hari",
                    };
                    $notifications->createOnce(
                        $invoice->owner,
                        BusinessNotificationType::InvoiceDueSoon,
                        "invoice-due:{$invoice->id}:{$invoice->due_date->format('Y-m-d')}",
                        'Jatuh Tempo Mendekat',
                        "Invoice #{$invoice->number} untuk {$invoice->customer_name} {$deadline}.",
                        NotificationTone::Yellow,
                        NotificationIcon::Alert,
                        "/invoices/{$invoice->id}",
                        ['invoiceId' => $invoice->id, 'dueDate' => $invoice->due_date->toDateString(), 'daysRemaining' => $days],
                    );
                }
            });
    }

    public function uniqueId(): string
    {
        return CarbonImmutable::now(config('notifications.timezone'))->toDateString();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
