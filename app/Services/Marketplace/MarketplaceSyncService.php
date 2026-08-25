<?php

namespace App\Services\Marketplace;

use App\Enums\Marketplace\ConnectionStatus;
use App\Exceptions\Marketplace\MarketplaceException;
use App\Exceptions\SubscriptionAccessDeniedException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Services\Customer\CustomerService;
use App\Services\Customer\WhatsappNormalizer;
use App\Services\Invoice\InvoiceService;
use App\Services\Marketplace\Support\MarketplaceOrderData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Mengubah pesanan marketplace menjadi data InvoiceHub.
 *
 * Semua penulisan tetap lewat service domain yang sudah ada (CustomerService dan
 * InvoiceService), jadi penomoran, batas paket, perhitungan pajak, dan event
 * berjalan persis seperti kalau pengguna memasukkannya sendiri.
 */
class MarketplaceSyncService
{
    public function __construct(
        private readonly MarketplaceProviderFactory $providers,
        private readonly CustomerService $customers,
        private readonly InvoiceService $invoices,
        private readonly WhatsappNormalizer $whatsapp,
    ) {}

    /**
     * Menarik pesanan baru dari platform lalu memetakannya.
     *
     * @return array{fetched: int, stored: int, imported: int, skipped: int, failed: int}
     */
    public function sync(MarketplaceConnection $connection): array
    {
        if ($connection->status !== ConnectionStatus::Connected) {
            throw new MarketplaceException('Sambungan belum aktif.', 'MARKETPLACE_NOT_CONNECTED');
        }

        $since = $connection->synced_until
            ?? CarbonImmutable::now()->subDays((int) config('marketplaces.initial_lookback_days', 30));

        try {
            $orders = $this->providers->make($connection->platform)->fetchOrders($connection, $since->toImmutable());
        } catch (MarketplaceException $exception) {
            $connection->update([
                'status' => ConnectionStatus::Error->value,
                'last_sync_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $stored = $this->storeOrders($connection, $orders);
        $mapped = $this->mapPendingOrders($connection);

        $connection->update([
            'last_synced_at' => now(),
            'synced_until' => now(),
            'last_sync_error' => null,
            'imported_order_count' => $connection->orders()->where('sync_status', MarketplaceOrder::SYNC_IMPORTED)->count(),
        ]);

        return ['fetched' => count($orders), 'stored' => $stored, ...$mapped];
    }

    /**
     * Menyimpan pesanan mentah. Pesanan yang sudah pernah masuk diperbarui, tidak digandakan.
     *
     * @param  list<MarketplaceOrderData>  $orders
     */
    public function storeOrders(MarketplaceConnection $connection, array $orders): int
    {
        $stored = 0;

        foreach ($orders as $order) {
            if ($order->externalOrderId === '') {
                continue;
            }

            $existing = MarketplaceOrder::query()
                ->where('marketplace_connection_id', $connection->id)
                ->where('external_order_id', $order->externalOrderId)
                ->first();

            if ($existing !== null) {
                // Status pesanan bisa berubah, tetapi hasil pemetaan tidak diulang.
                $existing->update(['status' => $order->status, 'raw_payload' => $order->raw]);

                continue;
            }

            MarketplaceOrder::query()->create([
                ...$order->toAttributes(),
                'user_id' => $connection->user_id,
                'marketplace_connection_id' => $connection->id,
                'sync_status' => MarketplaceOrder::SYNC_PENDING,
            ]);
            $stored++;
        }

        return $stored;
    }

    /**
     * Memetakan pesanan yang belum diproses menjadi pelanggan dan invoice.
     *
     * @return array{imported: int, skipped: int, failed: int}
     */
    public function mapPendingOrders(MarketplaceConnection $connection): array
    {
        $user = $connection->owner;
        $result = ['imported' => 0, 'skipped' => 0, 'failed' => 0];

        $pending = MarketplaceOrder::query()
            ->where('marketplace_connection_id', $connection->id)
            ->where('sync_status', MarketplaceOrder::SYNC_PENDING)
            ->orderBy('ordered_at')
            ->get();

        foreach ($pending as $order) {
            try {
                $this->mapOrder($user, $connection, $order);
                $result['imported']++;
            } catch (SubscriptionAccessDeniedException $exception) {
                // Batas invoice paket tercapai: hentikan, jangan tandai gagal.
                $order->update(['sync_status' => MarketplaceOrder::SYNC_PENDING, 'sync_error' => $exception->getMessage()]);
                break;
            } catch (ValidationException $exception) {
                $order->update([
                    'sync_status' => MarketplaceOrder::SYNC_SKIPPED,
                    'sync_error' => implode(' ', collect($exception->errors())->flatten()->all()),
                ]);
                $result['skipped']++;
            } catch (Throwable $exception) {
                $order->update([
                    'sync_status' => MarketplaceOrder::SYNC_FAILED,
                    'sync_error' => mb_substr($exception->getMessage(), 0, 500),
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function mapOrder(User $user, MarketplaceConnection $connection, MarketplaceOrder $order): void
    {
        DB::transaction(function () use ($user, $connection, $order): void {
            $customer = $this->resolveCustomer($user, $connection, $order);
            $invoice = $this->createInvoice($user, $connection, $order, $customer);

            $order->update([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'sync_status' => MarketplaceOrder::SYNC_IMPORTED,
                'sync_error' => null,
            ]);
        }, 3);
    }

    /**
     * Pembeli dicocokkan lewat nomor WhatsApp yang sudah dinormalisasi, sehingga
     * pembeli yang sama pada pesanan berbeda tidak menghasilkan pelanggan ganda.
     */
    private function resolveCustomer(User $user, MarketplaceConnection $connection, MarketplaceOrder $order): Customer
    {
        if (blank($order->buyer_phone)) {
            throw ValidationException::withMessages([
                'buyerPhone' => ['Pesanan tidak menyertakan nomor telepon pembeli, jadi pelanggan tidak dapat dibuat.'],
            ]);
        }

        $phone = $this->whatsapp->normalize((string) $order->buyer_phone);

        $existing = Customer::query()
            ->where('user_id', $user->id)
            ->where('whatsapp_normalized', $phone['normalized'])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->customers->create($user, [
            'name' => $order->buyer_name,
            'email' => $order->buyer_email,
            'whatsapp' => $order->buyer_phone,
            'city' => $order->buyer_city,
            'address' => $order->buyer_address,
            'source' => $connection->platform->customerSource(),
        ]);
    }

    private function createInvoice(User $user, MarketplaceConnection $connection, MarketplaceOrder $order, Customer $customer): Invoice
    {
        $items = [];

        foreach ($order->items ?? [] as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = max(0, (int) ($item['unitPrice'] ?? 0));

            if ($unitPrice === 0) {
                continue;
            }

            $items[] = [
                'description' => mb_substr((string) ($item['description'] ?? 'Produk'), 0, 255),
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
            ];
        }

        // Ongkos kirim dijadikan baris tersendiri supaya ikut tertagih dan terlihat
        // pada deteksi kebocoran, bukan lenyap di dalam total.
        if ($order->shipping_fee > 0) {
            $items[] = [
                'description' => 'Ongkos kirim',
                'quantity' => 1,
                'unitPrice' => (int) $order->shipping_fee,
            ];
        }

        if ($items === []) {
            $items[] = [
                'description' => sprintf('Pesanan %s %s', $connection->platform->label(), $order->order_number ?? $order->external_order_id),
                'quantity' => 1,
                'unitPrice' => max(0, (int) $order->total_amount),
            ];
        }

        $orderedAt = $order->ordered_at ?? CarbonImmutable::now();

        return $this->invoices->create($user, [
            'customerId' => $customer->id,
            'issueDate' => $orderedAt->format('Y-m-d'),
            'dueDate' => $orderedAt->addDays((int) config('marketplaces.invoice.due_days', 7))->format('Y-m-d'),
            'status' => (string) config('marketplaces.invoice.status', Invoice::STATUS_UNPAID),
            'discountAmount' => (int) $order->discount_amount,
            'notes' => sprintf(
                'Dibuat otomatis dari pesanan %s #%s.',
                $connection->platform->label(),
                $order->order_number ?? $order->external_order_id,
            ),
            'items' => $items,
        ]);
    }
}
