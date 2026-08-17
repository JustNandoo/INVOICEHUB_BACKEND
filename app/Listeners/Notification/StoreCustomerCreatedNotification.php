<?php

namespace App\Listeners\Notification;

use App\Enums\Notification\BusinessNotificationType;
use App\Enums\Notification\NotificationIcon;
use App\Enums\Notification\NotificationTone;
use App\Events\Customer\CustomerCreated;
use App\Models\Customer;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class StoreCustomerCreatedNotification implements ShouldQueueAfterCommit
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(CustomerCreated $event): void
    {
        $customer = Customer::query()->with('owner')->find($event->customerId);

        if (! $customer) {
            return;
        }

        $this->notifications->createOnce(
            $customer->owner,
            BusinessNotificationType::CustomerCreated,
            "customer-created:{$customer->id}",
            'Pelanggan Baru',
            $customer->name.' berhasil ditambahkan ke daftar pelanggan.',
            NotificationTone::Muted,
            NotificationIcon::Customer,
            "/customers/{$customer->id}",
            ['customerId' => $customer->id],
        );
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
