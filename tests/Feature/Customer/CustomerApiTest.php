<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_invoice_ignores_drafts_and_returns_the_newest_issued_invoice(): void
    {
        $user = User::factory()->create();
        $this->activatePlan($user, 'basic');
        $customer = Customer::factory()->for($user, 'owner')->create();

        Invoice::factory()->for($user, 'owner')->for($customer)->create([
            'number' => 'INV-ISSUED', 'status' => Invoice::STATUS_UNPAID,
            'issue_date' => today()->subDays(5), 'due_date' => today()->addMonth(),
        ]);
        // Draft lebih baru: tidak boleh menutupi invoice yang sudah diterbitkan.
        Invoice::factory()->for($user, 'owner')->for($customer)->create([
            'number' => 'INV-DRAFT', 'status' => Invoice::STATUS_DRAFT,
            'issue_date' => today(), 'due_date' => today()->addMonth(),
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('data.customers.0.lastInvoice.invoiceNumber', 'INV-ISSUED');
    }

    public function test_customer_api_requires_verified_authentication(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
        $user = User::factory()->unverified()->create();

        $this->withToken($this->token($user))->getJson('/api/v1/customers')->assertForbidden();
    }

    public function test_user_can_create_customers_with_normalized_whatsapp_and_sequential_codes(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);
        $payload = [
            'name' => 'Toko Makmur Raya', 'whatsapp' => '0813 5555 4444',
            'email' => 'halo@makmur.id', 'city' => 'Surabaya',
            'address' => 'Jl. Pemuda No. 7', 'source' => 'tokopedia',
        ];

        $this->withToken($token)->postJson('/api/v1/customers', $payload)
            ->assertCreated()->assertJsonPath('data.customer.customerCode', 'CUST-001')
            ->assertJsonPath('data.customer.whatsapp', '+6281355554444')
            ->assertJsonPath('data.customer.source', 'tokopedia')
            ->assertJsonPath('data.customer.metrics.totalTransactionValue', 0);
        $this->withToken($token)->postJson('/api/v1/customers', [
            ...$payload, 'name' => 'Second Customer', 'whatsapp' => '0812 1111 2222',
        ])->assertCreated()->assertJsonPath('data.customer.customerCode', 'CUST-002');

        $this->withToken($token)->postJson('/api/v1/customers', [
            ...$payload, 'name' => 'Duplicate Phone', 'whatsapp' => '+62 813-5555-4444',
        ])->assertUnprocessable()->assertJsonValidationErrors('whatsapp');
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id, 'customer_code' => 'CUST-001', 'whatsapp_normalized' => '6281355554444',
        ]);
    }

    public function test_list_supports_search_filters_sorting_pagination_and_derived_metrics(): void
    {
        $user = User::factory()->create();
        $makmur = Customer::factory()->for($user, 'owner')->create([
            'customer_code' => 'CUST-001', 'name' => 'Toko Makmur Raya', 'source' => 'tokopedia', 'is_active' => true,
        ]);
        $inactive = Customer::factory()->for($user, 'owner')->create([
            'customer_code' => 'CUST-002', 'name' => 'Customer Inactive', 'is_active' => false,
        ]);
        $this->invoice($user, $makmur, 2_500_000, Invoice::STATUS_UNPAID);
        $this->invoice($user, $makmur, 4_000_000, Invoice::STATUS_PAID);
        $this->invoice($user, $inactive, 8_000_000, Invoice::STATUS_PAID);
        Customer::factory()->create(['name' => 'Toko Makmur Milik Orang']);
        $token = $this->token($user);

        $this->withToken($token)->getJson('/api/v1/customers?search=Makmur&status=active&hasOutstanding=true&source=tokopedia&perPage=5')
            ->assertOk()->assertJsonCount(1, 'data.customers')
            ->assertJsonPath('data.customers.0.customerCode', 'CUST-001')
            ->assertJsonPath('data.customers.0.metrics.totalInvoiceCount', 2)
            ->assertJsonPath('data.customers.0.metrics.totalTransactionValue', 6_500_000)
            ->assertJsonPath('data.customers.0.metrics.totalPaid', 4_000_000)
            ->assertJsonPath('data.customers.0.metrics.totalOutstanding', 2_500_000)
            ->assertJsonPath('data.customers.0.lastInvoice.invoiceNumber', 'INV-2026-002')
            ->assertJsonPath('data.pagination.total', 1);

        $this->withToken($token)->getJson('/api/v1/customers?status=inactive')
            ->assertOk()->assertJsonCount(1, 'data.customers')
            ->assertJsonPath('data.customers.0.name', 'Customer Inactive');
    }

    public function test_summary_matches_customer_cards_for_selected_period(): void
    {
        $user = User::factory()->create();
        $first = Customer::factory()->for($user, 'owner')->create(['is_active' => true, 'created_at' => '2026-10-03 10:00:00']);
        $second = Customer::factory()->for($user, 'owner')->create(['is_active' => true, 'created_at' => '2026-09-01 10:00:00']);
        Customer::factory()->for($user, 'owner')->create(['is_active' => false, 'created_at' => '2026-08-01 10:00:00']);
        $this->invoice($user, $first, 6_000_000, Invoice::STATUS_UNPAID, '2026-10-05');
        $this->invoice($user, $second, 10_000_000, Invoice::STATUS_PAID, '2026-10-07');

        $this->withToken($this->token($user))->getJson('/api/v1/customers/summary?month=10&year=2026')
            ->assertOk()->assertJsonPath('data.summary.totalCustomers', 3)
            ->assertJsonPath('data.summary.activeCustomers', 2)
            ->assertJsonPath('data.summary.newCustomersThisMonth', 1)
            ->assertJsonPath('data.summary.customersWithOutstanding', 1)
            ->assertJsonPath('data.summary.totalOutstanding', 6_000_000)
            ->assertJsonPath('data.summary.periodTransactionValue', 16_000_000)
            ->assertJsonPath('data.summary.averageMonthlyTransactionValue', 8_000_000);
    }

    public function test_detail_and_customer_invoice_history_return_complete_data(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user, 'owner')->create(['customer_code' => 'CUST-042']);
        $invoice = $this->invoice($user, $customer, 450_000, Invoice::STATUS_UNPAID, '2026-10-12');
        $token = $this->token($user);

        $this->withToken($token)->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()->assertJsonPath('data.customer.customerCode', 'CUST-042')
            ->assertJsonPath('data.customer.metrics.totalOutstanding', 450_000)
            ->assertJsonPath('data.customer.lastInvoice.id', $invoice->id);

        $this->withToken($token)->getJson("/api/v1/customers/{$customer->id}/invoices?status=unpaid")
            ->assertOk()->assertJsonCount(1, 'data.invoices')
            ->assertJsonPath('data.customer.customerCode', 'CUST-042')
            ->assertJsonPath('data.invoices.0.invoiceNumber', 'INV-2026-001');
    }

    public function test_user_can_update_customer_but_cannot_delete_one_with_outstanding_invoice(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user, 'owner')->create();
        $invoice = $this->invoice($user, $customer, 900_000, Invoice::STATUS_UNPAID);
        $token = $this->token($user);

        $this->withToken($token)->patchJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Updated Customer', 'whatsapp' => '0812 9999 8888', 'isActive' => false,
        ])->assertOk()->assertJsonPath('data.customer.name', 'Updated Customer')
            ->assertJsonPath('data.customer.whatsapp', '+6281299998888')
            ->assertJsonPath('data.customer.isActive', false);

        $this->withToken($token)->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('customer');
        $invoice->update(['status' => Invoice::STATUS_PAID, 'paid_amount' => 900_000, 'balance_due' => 0, 'paid_at' => now()]);
        $this->withToken($token)->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertOk()->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'customer_id' => $customer->id]);
    }

    public function test_autocomplete_only_returns_active_customers_owned_by_current_user(): void
    {
        $user = User::factory()->create();
        Customer::factory()->for($user, 'owner')->create(['name' => 'Toko Makmur Aktif', 'is_active' => true]);
        Customer::factory()->for($user, 'owner')->create(['name' => 'Toko Makmur Nonaktif', 'is_active' => false]);
        Customer::factory()->create(['name' => 'Toko Makmur Orang Lain']);

        $this->withToken($this->token($user))->getJson('/api/v1/customers/search?search=Makmur')
            ->assertOk()->assertJsonCount(1, 'data.customers')
            ->assertJsonPath('data.customers.0.name', 'Toko Makmur Aktif');
    }

    public function test_other_users_cannot_read_update_or_delete_customer(): void
    {
        $owner = User::factory()->create();
        $customer = Customer::factory()->for($owner, 'owner')->create();
        $intruder = User::factory()->create();
        $token = $this->token($intruder);

        $this->withToken($token)->getJson("/api/v1/customers/{$customer->id}")->assertNotFound();
        $this->withToken($token)->patchJson("/api/v1/customers/{$customer->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->withToken($token)->deleteJson("/api/v1/customers/{$customer->id}")->assertNotFound();
    }

    private function invoice(
        User $user,
        Customer $customer,
        int $amount,
        string $status,
        string $issueDate = '2026-10-01',
    ): Invoice {
        return Invoice::factory()->for($user, 'owner')->for($customer)->create([
            'number' => 'INV-2026-'.str_pad((string) (Invoice::query()->count() + 1), 3, '0', STR_PAD_LEFT),
            'customer_name' => $customer->name, 'customer_email' => $customer->email,
            'customer_whatsapp' => $customer->whatsapp, 'status' => $status,
            'issue_date' => $issueDate, 'due_date' => '2026-11-01',
            'subtotal' => $amount, 'total_amount' => $amount,
            'paid_amount' => $status === Invoice::STATUS_PAID ? $amount : 0,
            'balance_due' => $status === Invoice::STATUS_PAID ? 0 : $amount,
            'paid_at' => $status === Invoice::STATUS_PAID ? $issueDate.' 12:00:00' : null,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('customer-test')->plainTextToken;
    }
}
