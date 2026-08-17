<?php

namespace App\Services\Invoice;

use App\Events\Invoice\InvoicePaid;
use App\Events\Invoice\InvoicePaymentRecorded;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceActivity;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\Subscription\EntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly InvoiceNumberService $numbers,
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Invoice
    {
        return DB::transaction(function () use ($user, $data): Invoice {
            // Serializes invoice creation per owner so plan limits cannot be bypassed by concurrent requests.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->entitlements->assertCanCreateInvoice($user);

            $issueDate = CarbonImmutable::createFromFormat('Y-m-d', $data['issueDate'])->startOfDay();
            $customer = $this->customerSnapshot($user, $data);
            $taxRate = $this->taxRateToBasisPoints($data['taxRate'] ?? 0);
            $totals = $this->calculator->calculate(
                $data['items'],
                $taxRate,
                (int) ($data['discountAmount'] ?? 0),
            );

            $invoice = Invoice::query()->create([
                'user_id' => $user->id,
                'customer_id' => $customer['id'],
                'number' => $this->numbers->next($user, $issueDate->year),
                'status' => $data['status'] ?? Invoice::STATUS_DRAFT,
                'issuer_name' => $user->business_name,
                'issuer_email' => $user->email,
                'issuer_address' => $data['issuerAddress'] ?? null,
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_whatsapp' => $customer['whatsapp'],
                'customer_address' => $customer['address'],
                'issue_date' => $data['issueDate'],
                'due_date' => $data['dueDate'],
                'subtotal' => $totals['subtotal'],
                'tax_rate_basis_points' => $totals['taxRateBasisPoints'],
                'tax_amount' => $totals['taxAmount'],
                'discount_amount' => $totals['discountAmount'],
                'total_amount' => $totals['totalAmount'],
                'paid_amount' => 0,
                'balance_due' => $totals['totalAmount'],
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->items()->createMany($totals['items']);
            $this->recordActivity(
                $invoice,
                $user,
                'created',
                'Invoice Dibuat',
                "{$invoice->number} dibuat oleh {$user->name}.",
            );

            return $this->loadDetail($invoice);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, User $user, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $user, $data): Invoice {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->guardCanUpdate($invoice, $data);

            $issueDate = $data['issueDate'] ?? $invoice->issue_date->format('Y-m-d');
            $dueDate = $data['dueDate'] ?? $invoice->due_date->format('Y-m-d');

            if ($dueDate < $issueDate) {
                throw ValidationException::withMessages([
                    'dueDate' => ['Tanggal jatuh tempo tidak boleh sebelum tanggal invoice.'],
                ]);
            }

            $attributes = Arr::whereNotNull([
                'issuer_address' => array_key_exists('issuerAddress', $data) ? $data['issuerAddress'] : null,
                'issue_date' => $data['issueDate'] ?? null,
                'due_date' => $data['dueDate'] ?? null,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : null,
            ]);

            if (array_key_exists('notes', $data) && $data['notes'] === null) {
                $attributes['notes'] = null;
            }
            if (array_key_exists('issuerAddress', $data) && $data['issuerAddress'] === null) {
                $attributes['issuer_address'] = null;
            }

            if (isset($data['customerId']) || isset($data['customer'])) {
                $customer = $this->customerSnapshot($user, $data);
                $attributes = array_merge($attributes, [
                    'customer_id' => $customer['id'],
                    'customer_name' => $customer['name'],
                    'customer_email' => $customer['email'],
                    'customer_whatsapp' => $customer['whatsapp'],
                    'customer_address' => $customer['address'],
                ]);
            }

            $mustRecalculate = isset($data['items'])
                || array_key_exists('taxRate', $data)
                || array_key_exists('discountAmount', $data);

            if ($mustRecalculate) {
                $items = $data['items'] ?? $invoice->items->map(fn ($item): array => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_price,
                ])->all();
                $totals = $this->calculator->calculate(
                    $items,
                    array_key_exists('taxRate', $data)
                        ? $this->taxRateToBasisPoints($data['taxRate'])
                        : $invoice->tax_rate_basis_points,
                    (int) ($data['discountAmount'] ?? $invoice->discount_amount),
                );
                $attributes = array_merge($attributes, [
                    'subtotal' => $totals['subtotal'],
                    'tax_rate_basis_points' => $totals['taxRateBasisPoints'],
                    'tax_amount' => $totals['taxAmount'],
                    'discount_amount' => $totals['discountAmount'],
                    'total_amount' => $totals['totalAmount'],
                    'balance_due' => $totals['totalAmount'] - $invoice->paid_amount,
                ]);

                if (isset($data['items'])) {
                    $invoice->items()->delete();
                    $invoice->items()->createMany($totals['items']);
                }
            }

            $invoice->update($attributes);
            $this->recordActivity($invoice, $user, 'updated', 'Invoice Diperbarui', 'Detail invoice diperbarui.');

            return $this->loadDetail($invoice->refresh());
        }, 3);
    }

    public function deleteOrVoid(Invoice $invoice, User $user): string
    {
        return DB::transaction(function () use ($invoice, $user): string {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status === Invoice::STATUS_PAID) {
                throw ValidationException::withMessages([
                    'invoice' => ['Invoice lunas tidak dapat dihapus atau dibatalkan.'],
                ]);
            }

            if ($invoice->status === Invoice::STATUS_DRAFT) {
                $invoice->delete();

                return 'deleted';
            }

            if ($invoice->status === Invoice::STATUS_VOID) {
                throw ValidationException::withMessages(['invoice' => ['Invoice sudah dibatalkan.']]);
            }

            $invoice->update(['status' => Invoice::STATUS_VOID, 'voided_at' => now()]);
            $this->recordActivity($invoice, $user, 'voided', 'Invoice Dibatalkan', 'Invoice dibatalkan oleh pemilik akun.');

            return 'voided';
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{payment: InvoicePayment, invoice: Invoice}
     */
    public function recordPayment(Invoice $invoice, User $user, array $data): array
    {
        return DB::transaction(function () use ($invoice, $user, $data): array {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== Invoice::STATUS_UNPAID) {
                throw ValidationException::withMessages([
                    'invoice' => ['Pembayaran hanya dapat dicatat untuk invoice yang belum lunas.'],
                ]);
            }

            $amount = (int) $data['amount'];
            if ($amount > $invoice->balance_due) {
                throw ValidationException::withMessages([
                    'amount' => ['Nominal pembayaran melebihi sisa tagihan.'],
                ]);
            }

            $payment = $invoice->payments()->create([
                'recorded_by' => $user->id,
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paidAt'],
                'notes' => $data['notes'] ?? null,
            ]);
            $paidAmount = $invoice->paid_amount + $amount;
            $balanceDue = $invoice->total_amount - $paidAmount;
            $isPaid = $balanceDue === 0;
            $invoice->update([
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'status' => $isPaid ? Invoice::STATUS_PAID : Invoice::STATUS_UNPAID,
                'paid_at' => $isPaid ? $data['paidAt'] : null,
            ]);

            $this->recordActivity(
                $invoice,
                $user,
                $isPaid ? 'paid' : 'payment_received',
                $isPaid ? 'Pembayaran Diterima (Lunas)' : 'Pembayaran Sebagian Diterima',
                'Pembayaran sebesar Rp '.number_format($amount, 0, ',', '.').' melalui '.$data['method'].'.',
                ['paymentId' => $payment->id, 'amount' => $amount, 'method' => $data['method']],
            );
            InvoicePaymentRecorded::dispatch($invoice->id, $payment->id);
            if ($isPaid) {
                InvoicePaid::dispatch($invoice->id, $payment->id);
            }

            return ['payment' => $payment, 'invoice' => $this->loadDetail($invoice->refresh())];
        }, 3);
    }

    public function markSent(Invoice $invoice, User $user, string $channel, string $recipient): Invoice
    {
        return DB::transaction(function () use ($invoice, $user, $channel, $recipient): Invoice {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_VOID], true)) {
                throw ValidationException::withMessages([
                    'invoice' => ['Invoice lunas atau dibatalkan tidak dapat dikirim ulang.'],
                ]);
            }

            $wasSent = $invoice->sent_at !== null;
            $invoice->update([
                'status' => Invoice::STATUS_UNPAID,
                'sent_at' => now(),
            ]);
            $this->recordActivity(
                $invoice,
                $user,
                $wasSent ? 'resent' : 'sent',
                $wasSent ? 'Invoice Dikirim Ulang' : 'Dikirim ke Pelanggan',
                'Via '.ucfirst($channel).' ke '.$recipient.'.',
                ['channel' => $channel, 'recipient' => $recipient],
            );

            return $this->loadDetail($invoice->refresh());
        }, 3);
    }

    public function loadDetail(Invoice $invoice): Invoice
    {
        return $invoice->load(['items', 'payments', 'activities']);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function recordActivity(
        Invoice $invoice,
        ?User $actor,
        string $type,
        string $title,
        ?string $description = null,
        ?array $metadata = null,
    ): InvoiceActivity {
        return $invoice->activities()->create([
            'actor_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int|null, name: string, email: string|null, whatsapp: string|null, address: string|null}
     */
    private function customerSnapshot(User $user, array $data): array
    {
        if (! empty($data['customerId'])) {
            $customer = Customer::query()
                ->where('user_id', $user->id)
                ->findOrFail((int) $data['customerId']);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'whatsapp' => $customer->whatsapp,
                'address' => $customer->address,
            ];
        }

        $customer = $data['customer'] ?? null;
        if (! is_array($customer) || empty($customer['name'])) {
            throw ValidationException::withMessages([
                'customer' => ['Pelanggan atau customerId wajib dipilih.'],
            ]);
        }

        return [
            'id' => null,
            'name' => trim((string) $customer['name']),
            'email' => $customer['email'] ?? null,
            'whatsapp' => $customer['whatsapp'] ?? null,
            'address' => $customer['address'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardCanUpdate(Invoice $invoice, array $data): void
    {
        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_VOID], true)) {
            throw ValidationException::withMessages([
                'invoice' => ['Invoice lunas atau dibatalkan tidak dapat diubah.'],
            ]);
        }

        if ($invoice->status === Invoice::STATUS_UNPAID) {
            $unsupported = array_diff(array_keys($data), ['dueDate', 'notes']);
            if ($unsupported !== []) {
                throw ValidationException::withMessages([
                    'invoice' => ['Setelah diterbitkan, hanya jatuh tempo dan catatan yang dapat diubah.'],
                ]);
            }
        }
    }

    private function taxRateToBasisPoints(int|float|string $taxRate): int
    {
        return (int) round(((float) $taxRate) * 100);
    }
}
