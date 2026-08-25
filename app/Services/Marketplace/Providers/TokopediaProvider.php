<?php

namespace App\Services\Marketplace\Providers;

use App\Enums\Marketplace\MarketplacePlatform;
use App\Exceptions\Marketplace\MarketplaceException;
use App\Models\MarketplaceConnection;
use App\Services\Marketplace\Contracts\MarketplaceProvider;
use App\Services\Marketplace\Support\MarketplaceCredentials;
use App\Services\Marketplace\Support\MarketplaceOrderData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Tokopedia Seller API (Mitra Tokopedia / Fleet Service).
 *
 * Berbeda dari Shopee dan Lazada, Tokopedia memakai OAuth client credentials pada
 * level fleet: token diambil dengan Basic Auth client_id:client_secret, lalu toko
 * diakses lewat fs_id. Kredensial diperoleh setelah menjadi mitra resmi.
 */
class TokopediaProvider implements MarketplaceProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function platform(): MarketplacePlatform
    {
        return MarketplacePlatform::Tokopedia;
    }

    /**
     * Tokopedia tidak memakai alur redirect pengguna seperti Shopee; token diambil
     * langsung dengan kredensial mitra, jadi tidak ada halaman izin untuk dibuka.
     */
    public function authorizationUrl(MarketplaceConnection $connection): string
    {
        throw MarketplaceException::unsupported(
            'Tokopedia',
            'alur izin lewat browser. Hubungkan memakai kredensial mitra melalui endpoint sinkronisasi',
        );
    }

    public function exchangeCallback(MarketplaceConnection $connection, array $query): MarketplaceCredentials
    {
        $this->assertConfigured();

        $response = Http::timeout(30)
            ->withBasicAuth((string) $this->config['client_id'], (string) $this->config['client_secret'])
            ->asForm()
            ->post((string) $this->config['auth_url'], ['grant_type' => 'client_credentials']);

        if ($response->failed()) {
            throw new MarketplaceException('Tokopedia menolak permintaan token: '.$response->body(), 'MARKETPLACE_TOKEN_FAILED');
        }

        $body = $response->json();

        return new MarketplaceCredentials(
            accessToken: (string) ($body['access_token'] ?? ''),
            refreshToken: null,
            expiresAt: isset($body['expires_in'])
                ? CarbonImmutable::now()->addSeconds((int) $body['expires_in'])
                : null,
            shopId: (string) ($query['shop_id'] ?? $this->config['fs_id']),
            shopName: $query['shop_name'] ?? null,
        );
    }

    public function fetchOrders(MarketplaceConnection $connection, CarbonImmutable $since): array
    {
        $this->assertConfigured();

        $response = Http::timeout(45)
            ->withToken((string) $connection->access_token)
            ->get(rtrim((string) $this->config['base_url'], '/').'/v2/order/list', [
                'fs_id' => $this->config['fs_id'],
                'shop_id' => $connection->shop_id,
                'from_date' => $since->timestamp,
                'to_date' => now()->timestamp,
                'page' => 1,
                'per_page' => 50,
            ]);

        if ($response->failed()) {
            throw new MarketplaceException('Gagal mengambil pesanan Tokopedia: '.$response->body(), 'MARKETPLACE_FETCH_FAILED');
        }

        $orders = [];

        foreach ($response->json('data') ?? [] as $order) {
            $buyer = $order['buyer'] ?? [];
            $destination = $order['recipient'] ?? [];
            $items = [];

            foreach ($order['products'] ?? [] as $product) {
                $items[] = [
                    'description' => (string) ($product['name'] ?? 'Produk'),
                    'quantity' => (int) ($product['quantity'] ?? 1),
                    'unitPrice' => (int) round((float) ($product['price'] ?? 0)),
                ];
            }

            $orders[] = new MarketplaceOrderData(
                externalOrderId: (string) ($order['order_id'] ?? ''),
                orderNumber: isset($order['invoice_ref_num']) ? (string) $order['invoice_ref_num'] : null,
                status: (string) ($order['order_status'] ?? 'unknown'),
                buyerName: (string) ($destination['name'] ?? $buyer['name'] ?? 'Pembeli Tokopedia'),
                buyerPhone: $destination['phone'] ?? ($buyer['phone'] ?? null),
                buyerEmail: $buyer['email'] ?? null,
                buyerCity: $destination['city'] ?? null,
                buyerAddress: $destination['address'] ?? null,
                totalAmount: (int) round((float) ($order['payment']['total'] ?? 0)),
                shippingFee: (int) round((float) ($order['logistic']['shipping_cost'] ?? 0)),
                platformFee: 0,
                discountAmount: 0,
                items: $items,
                orderedAt: CarbonImmutable::createFromTimestamp((int) ($order['create_time'] ?? now()->timestamp)),
                raw: $order,
            );
        }

        return $orders;
    }

    private function assertConfigured(): void
    {
        if (! MarketplacePlatform::Tokopedia->isConfigured()) {
            throw MarketplaceException::notConfigured('Tokopedia');
        }
    }
}
