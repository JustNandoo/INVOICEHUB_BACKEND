<?php

namespace Tests\Feature\Notification;

use App\Enums\Notification\BusinessNotificationType;
use App\Events\Customer\CustomerCreated;
use App\Events\Invoice\InvoicePaid;
use App\Events\Invoice\InvoicePaymentRecorded;
use App\Jobs\Notification\CreateUpcomingInvoiceDueNotifications;
use App\Listeners\Notification\EvaluateRevenueTargetNotification;
use App\Listeners\Notification\StoreCustomerCreatedNotification;
use App\Listeners\Notification\StoreInvoicePaidNotification;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Customer\CustomerService;
use App\Services\Invoice\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_paid_listener_creates_exactly_one_notification(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->for($user, 'owner')->paid()->create([
            'number' => 'INV-2026-042', 'customer_name' => 'Toko Budi', 'total_amount' => 450_000,
            'paid_amount' => 450_000, 'balance_due' => 0,
        ]);
        $payment = $invoice->payments()->create([
            'recorded_by' => $user->id, 'amount' => 450_000, 'method' => 'bank_transfer', 'paid_at' => now(),
        ]);
        $listener = app(StoreInvoicePaidNotification::class);

        $listener->handle(new InvoicePaid($invoice->id, $payment->id));
        $listener->handle(new InvoicePaid($invoice->id, $payment->id));

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => BusinessNotificationType::InvoicePaid->value,
            'dedupe_key' => "invoice-paid:{$invoice->id}",
        ]);
    }

    public function test_customer_created_listener_creates_a_customer_notification(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user, 'owner')->create(['name' => 'Toko Makmur Jaya']);

        app(StoreCustomerCreatedNotification::class)->handle(new CustomerCreated($customer->id));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => BusinessNotificationType::CustomerCreated->value,
            'dedupe_key' => "customer-created:{$customer->id}",
        ]);
    }

    public function test_due_invoice_job_is_idempotent_and_ignores_paid_invoices(): void
    {
        CarbonImmutable::setTestNow('2026-08-17 08:00:00');
        config()->set('notifications.timezone', 'Asia/Jakarta');
        $user = User::factory()->create();
        $due = Invoice::factory()->for($user, 'owner')->create(['due_date' => '2026-08-19']);
        Invoice::factory()->for($user, 'owner')->paid()->create(['due_date' => '2026-08-19']);
        $job = new CreateUpcomingInvoiceDueNotifications;

        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'type' => BusinessNotificationType::InvoiceDueSoon->value,
            'dedupe_key' => "invoice-due:{$due->id}:2026-08-19",
        ]);
        CarbonImmutable::setTestNow();
    }

    public function test_domain_services_dispatch_customer_and_invoice_events(): void
    {
        Event::fake([CustomerCreated::class, InvoicePaid::class, InvoicePaymentRecorded::class]);
        $user = User::factory()->create();
        $customer = app(CustomerService::class)->create($user, [
            'name' => 'New Customer', 'whatsapp' => '081234567890',
        ]);
        $invoice = Invoice::factory()->for($user, 'owner')->for($customer)->create([
            'total_amount' => 500_000, 'balance_due' => 500_000,
        ]);
        app(InvoiceService::class)->recordPayment($invoice, $user, [
            'amount' => 500_000, 'method' => 'bank_transfer', 'paidAt' => now(),
        ]);

        Event::assertDispatched(CustomerCreated::class, fn ($event) => $event->customerId === $customer->id);
        Event::assertDispatched(InvoicePaymentRecorded::class, fn ($event) => $event->invoiceId === $invoice->id);
        Event::assertDispatched(InvoicePaid::class, fn ($event) => $event->invoiceId === $invoice->id);
    }

    public function test_revenue_target_api_and_listener_create_notification_when_target_is_reached(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('target-test')->plainTextToken;
        $this->withToken($token)->putJson('/api/v1/revenue-targets/2026/8', ['amount' => 1_000_000])
            ->assertOk()->assertJsonPath('data.target.amount', 1_000_000)
            ->assertJsonPath('data.isReached', false);
        $invoice = Invoice::factory()->for($user, 'owner')->paid()->create([
            'total_amount' => 1_200_000, 'paid_amount' => 1_200_000, 'balance_due' => 0,
        ]);
        $payment = $invoice->payments()->create([
            'recorded_by' => $user->id, 'amount' => 1_200_000,
            'method' => 'bank_transfer', 'paid_at' => '2026-08-17 10:00:00',
        ]);

        app(EvaluateRevenueTargetNotification::class)->handle(new InvoicePaymentRecorded($invoice->id, $payment->id));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => BusinessNotificationType::RevenueTargetReached->value,
        ]);
        $this->withToken($token)->getJson('/api/v1/revenue-targets/2026/8')
            ->assertOk()->assertJsonPath('data.currentRevenue', 1_200_000)
            ->assertJsonPath('data.progressPercent', 100)
            ->assertJsonPath('data.isReached', true);
    }
}
