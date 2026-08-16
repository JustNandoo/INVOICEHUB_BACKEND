<?php

namespace Tests\Feature\Invoice;

use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_api_requires_a_verified_authenticated_user(): void
    {
        $this->getJson('/api/v1/invoices')->assertUnauthorized();

        $user = User::factory()->unverified()->create();
        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/invoices')
            ->assertForbidden();
    }

    public function test_user_can_create_draft_and_server_calculates_all_totals(): void
    {
        $response = $this->withToken($this->token())
            ->postJson('/api/v1/invoices', $this->payload([
                'taxRate' => 11,
                'discountAmount' => 50000,
            ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.invoice.invoiceNumber', 'INV-'.today()->year.'-001')
            ->assertJsonPath('data.invoice.status', 'draft')
            ->assertJsonPath('data.invoice.totals.subtotal', 425000)
            ->assertJsonPath('data.invoice.totals.taxAmount', 46750)
            ->assertJsonPath('data.invoice.totals.discountAmount', 50000)
            ->assertJsonPath('data.invoice.totals.totalAmount', 421750)
            ->assertJsonCount(2, 'data.invoice.items')
            ->assertJsonCount(1, 'data.invoice.activities');

        $this->assertDatabaseHas('invoice_items', [
            'description' => 'Kopi Robusta 1kg',
            'line_total' => 300000,
        ]);
    }

    public function test_invoice_number_sequence_is_per_owner_and_year(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $first = $this->actingAs($firstUser, 'sanctum')
            ->postJson('/api/v1/invoices', $this->payload())
            ->assertCreated();
        $second = $this->actingAs($firstUser, 'sanctum')
            ->postJson('/api/v1/invoices', $this->payload())
            ->assertCreated();
        $otherOwner = $this->actingAs($secondUser, 'sanctum')
            ->postJson('/api/v1/invoices', $this->payload())
            ->assertCreated();

        $this->assertSame('INV-'.today()->year.'-001', $first->json('data.invoice.invoiceNumber'));
        $this->assertSame('INV-'.today()->year.'-002', $second->json('data.invoice.invoiceNumber'));
        $this->assertSame('INV-'.today()->year.'-001', $otherOwner->json('data.invoice.invoiceNumber'));
    }

    public function test_user_can_list_search_filter_and_read_summary(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user, 'owner')->create([
            'number' => 'INV-2026-001',
            'customer_name' => 'PT Teknologi Nusantara',
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => today()->addWeek(),
            'total_amount' => 5200000,
            'balance_due' => 5200000,
        ]);
        Invoice::factory()->for($user, 'owner')->paid()->create([
            'number' => 'INV-2026-002',
            'customer_name' => 'CV Maju Bersama',
            'total_amount' => 12500000,
            'paid_amount' => 12500000,
        ]);
        Invoice::factory()->for($user, 'owner')->create([
            'number' => 'INV-2026-003',
            'customer_name' => 'Toko Terlambat',
            'due_date' => today()->subDay(),
            'total_amount' => 3500000,
            'balance_due' => 3500000,
        ]);
        Invoice::factory()->for(User::factory()->create(), 'owner')->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/invoices?search=Teknologi&status=unpaid')
            ->assertOk()
            ->assertJsonCount(1, 'data.invoices')
            ->assertJsonPath('data.invoices.0.invoiceNumber', 'INV-2026-001');

        $this->withToken($token)
            ->getJson('/api/v1/invoices?status=overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data.invoices')
            ->assertJsonPath('data.invoices.0.status', 'overdue');

        $this->withToken($token)
            ->getJson('/api/v1/invoices/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.totalInvoices', 3)
            ->assertJsonPath('data.summary.totalBilled', 21200000)
            ->assertJsonPath('data.summary.unpaidInvoices', 2)
            ->assertJsonPath('data.summary.unpaidAmount', 8700000)
            ->assertJsonPath('data.summary.overdueInvoices', 1)
            ->assertJsonPath('data.summary.overdueAmount', 3500000);
    }

    public function test_user_cannot_access_another_users_invoice(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $invoice = Invoice::factory()->for($owner, 'owner')->create();

        $this->withToken($intruder->createToken('test')->plainTextToken)
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertNotFound();
    }

    public function test_user_can_update_draft_and_totals_are_recalculated(): void
    {
        [$user, $invoice] = $this->createInvoiceThroughApi();

        $this->withToken($user->createToken('update')->plainTextToken)
            ->patchJson("/api/v1/invoices/{$invoice->id}", [
                'dueDate' => today()->addMonths(2)->format('Y-m-d'),
                'taxRate' => 10,
                'items' => [[
                    'description' => 'Paket Enterprise',
                    'quantity' => 2,
                    'unitPrice' => 1000000,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.invoice.totals.subtotal', 2000000)
            ->assertJsonPath('data.invoice.totals.taxAmount', 200000)
            ->assertJsonPath('data.invoice.totals.totalAmount', 2200000)
            ->assertJsonCount(2, 'data.invoice.activities');
    }

    public function test_whatsapp_delivery_publishes_draft_and_records_activity(): void
    {
        [$user, $invoice] = $this->createInvoiceThroughApi();
        $this->activatePlan($user, 'basic');

        $this->withToken($user->createToken('send')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/send", [
                'channel' => 'whatsapp',
            ])
            ->assertOk()
            ->assertJsonPath('data.invoice.status', 'unpaid')
            ->assertJsonPath('data.delivery.channel', 'whatsapp')
            ->assertJsonPath('data.delivery.recipient', '6281234567890')
            ->assertJson(fn ($json) => $json->whereType('data.delivery.actionUrl', 'string')->etc());

        $this->assertDatabaseHas('invoice_activities', ['invoice_id' => $invoice->id, 'type' => 'sent']);
    }

    public function test_email_delivery_sends_pdf_attachment(): void
    {
        Mail::fake();
        [$user, $invoice] = $this->createInvoiceThroughApi();

        $this->withToken($user->createToken('send')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/send", ['channel' => 'email'])
            ->assertOk()
            ->assertJsonPath('data.delivery.recipient', 'budi@example.com');

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail): bool {
            return $mail->hasTo('budi@example.com') && count($mail->attachments()) === 1;
        });
    }

    public function test_partial_and_full_payments_update_invoice_balance_and_status(): void
    {
        [$user, $invoice] = $this->createInvoiceThroughApi(['status' => Invoice::STATUS_UNPAID]);
        $token = $user->createToken('pay')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'amount' => 100000,
                'method' => 'bank_transfer',
                'reference' => 'BCA-001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.invoice.status', 'unpaid')
            ->assertJsonPath('data.invoice.totals.paidAmount', 100000)
            ->assertJsonPath('data.invoice.totals.balanceDue', 325000);

        $this->withToken($token)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'amount' => 325000,
                'method' => 'bank_transfer',
            ])
            ->assertCreated()
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.invoice.totals.balanceDue', 0);

        $this->assertDatabaseCount('invoice_payments', 2);
    }

    public function test_payment_cannot_exceed_remaining_balance(): void
    {
        [$user, $invoice] = $this->createInvoiceThroughApi(['status' => Invoice::STATUS_UNPAID]);

        $this->withToken($user->createToken('pay')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'amount' => 500000,
                'method' => 'cash',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_delete_removes_draft_but_voids_issued_invoice(): void
    {
        [$user, $draft] = $this->createInvoiceThroughApi();
        $token = $user->createToken('delete')->plainTextToken;

        $this->withToken($token)
            ->deleteJson("/api/v1/invoices/{$draft->id}")
            ->assertOk()
            ->assertJsonPath('data.action', 'deleted');
        $this->assertSoftDeleted('invoices', ['id' => $draft->id]);

        $issuedResponse = $this->withToken($token)
            ->postJson('/api/v1/invoices', $this->payload(['status' => Invoice::STATUS_UNPAID]))
            ->assertCreated();
        $issuedId = $issuedResponse->json('data.invoice.id');

        $this->withToken($token)
            ->deleteJson("/api/v1/invoices/{$issuedId}")
            ->assertOk()
            ->assertJsonPath('data.action', 'voided');
        $this->assertDatabaseHas('invoices', ['id' => $issuedId, 'status' => Invoice::STATUS_VOID]);
    }

    public function test_user_can_download_a_real_pdf(): void
    {
        [$user, $invoice] = $this->createInvoiceThroughApi();

        $response = $this->withToken($user->createToken('pdf')->plainTextToken)
            ->get("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_customer_search_only_returns_current_users_customers(): void
    {
        $user = User::factory()->create();
        Customer::factory()->for($user, 'owner')->create(['name' => 'Toko Makmur Raya']);
        Customer::factory()->for(User::factory(), 'owner')->create(['name' => 'Toko Makmur Milik Orang']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/customers?search=Makmur')
            ->assertOk()
            ->assertJsonCount(1, 'data.customers')
            ->assertJsonPath('data.customers.0.name', 'Toko Makmur Raya');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{User, Invoice}
     */
    private function createInvoiceThroughApi(array $overrides = []): array
    {
        $user = User::factory()->create();
        $response = $this->withToken($user->createToken('create')->plainTextToken)
            ->postJson('/api/v1/invoices', $this->payload($overrides))
            ->assertCreated();

        return [$user, Invoice::query()->findOrFail($response->json('data.invoice.id'))];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer' => [
                'name' => 'Bapak Budi (Toko Makmur)',
                'email' => 'budi@example.com',
                'whatsapp' => '+62 812 3456 7890',
                'address' => 'Jl. Melati Blok C No. 12, Bandung',
            ],
            'issuerAddress' => 'Jl. Sudirman No. 45, Jakarta Selatan',
            'issueDate' => today()->format('Y-m-d'),
            'dueDate' => today()->addMonth()->format('Y-m-d'),
            'status' => Invoice::STATUS_DRAFT,
            'taxRate' => 0,
            'discountAmount' => 0,
            'items' => [
                ['description' => 'Kopi Robusta 1kg', 'quantity' => 2, 'unitPrice' => 150000],
                ['description' => 'Packaging Pouch', 'quantity' => 50, 'unitPrice' => 2500],
            ],
        ], $overrides);
    }

    private function token(): string
    {
        return User::factory()->create()->createToken('test')->plainTextToken;
    }
}
