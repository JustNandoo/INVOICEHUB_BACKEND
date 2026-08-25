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
 * Shopee Open Platform.
 *
 * Struktur permintaan dan penandatanganan mengikuti Shopee Open API v2: setiap
 * panggilan menyertakan partner_id, timestamp, dan sign berupa HMAC-SHA256 atas
 * base string. Nilai partner_id/partner_key hanya diperoleh setelah aplikasi Anda
 * disetujui di Shopee Open Platform.
 */
class ShopeeProvider implements MarketplaceProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function platform(): MarketplacePlatform
    {
        return MarketplacePlatform::Shopee;
    }

    public function authorizationUrl(MarketplaceConnection $connection): string
    {
        $this->assertConfigured();

        $path = (string) ($this->config['auth_path'] ?? '/api/v2/shop/auth_partner');
        $timestamp = now()->timestamp;
        $sign = $this->sign($path, $timestamp);

        $redirect = rtrim((string) config('marketplaces.redirect_url'), '/')
            .'?platform=shopee&state='.$connection->state_token;

        return rtrim((string) $this->config['base_url'], '/').$path.'?'.http_build_query([
            'partner_id' => $this->config['partner_id'],
            'timestamp' => $timestamp,
            'sign' => $sign,
            'redirect' => $redirect,
        ]);
    }

    public function exchangeCallback(MarketplaceConnection $connection, array $query): MarketplaceCredentials
    {
        $this->assertConfigured();

        $code = (string) ($query['code'] ?? '');
        $shopId = (string) ($query['shop_id'] ?? '');

        if ($code === '' || $shopId === '') {
            throw new MarketplaceException('Callback Shopee tidak menyertakan code atau shop_id.', 'MARKETPLACE_BAD_CALLBACK');
        }

        $path = '/api/v2/auth/token/get';
        $timestamp = now()->timestamp;

        $response = Http::timeout(30)->post(
            $this->url($path, $timestamp, $this->sign($path, $timestamp)),
            ['code' => $code, 'shop_id' => (int) $shopId, 'partner_id' => (int) $this->config['partner_id']],
        );

        if ($response->failed()) {
            throw new MarketplaceException('Shopee menolak penukaran token: '.$response->body(), 'MARKETPLACE_TOKEN_FAILED');
        }

        $body = $response->json();

        return new MarketplaceCredentials(
            accessToken: (string) ($body['access_token'] ?? ''),
            refreshToken: $body['refresh_token'] ?? null,
            expiresAt: isset($body['expire_in'])
                ? CarbonImmutable::now()->addSeconds((int) $body['expire_in'])
                : null,
            shopId: $shopId,
            shopName: $body['shop_name'] ?? null,
        );
    }

    public function fetchOrders(MarketplaceConnection $connection, CarbonImmutable $since): array
    {
        $this->assertConfigured();

        $path = '/api/v2/order/get_order_list';
        $timestamp = now()->timestamp;

        $response = Http::timeout(45)->get($this->url($path, $timestamp, $this->sign($path, $timestamp, $connection)), [
            'access_token' => $connection->access_token,
            'shop_id' => (int) $connection->shop_id,
            'time_range_field' => 'create_time',
            'time_from' => $since->timestamp,
            'time_to' => now()->timestamp,
            'page_size' => 50,
            'response_optional_fields' => 'order_status,total_amount,recipient_address,item_list,create_time',
        ]);

        if ($response->failed()) {
            throw new MarketplaceException('Gagal mengambil pesanan Shopee: '.$response->body(), 'MARKETPLACE_FETCH_FAILED');
        }

        $orders = [];

        foreach ($response->json('response.order_list') ?? [] as $order) {
            $orders[] = $this->toOrderData($order);
        }

        return $orders;
    }

    /** @param array<string, mixed> $order */
    private function toOrderData(array $order): MarketplaceOrderData
    {
        $address = $order['recipient_address'] ?? [];
        $items = [];

        foreach ($order['item_list'] ?? [] as $item) {
            $items[] = [
                'description' => (string) ($item['item_name'] ?? 'Produk'),
                'quantity' => (int) ($item['model_quantity_purchased'] ?? 1),
                'unitPrice' => (int) round((float) ($item['model_discounted_price'] ?? 0)),
            ];
        }

        return new MarketplaceOrderData(
            externalOrderId: (string) ($order['order_sn'] ?? ''),
            orderNumber: $order['order_sn'] ?? null,
            status: (string) ($order['order_status'] ?? 'unknown'),
            buyerName: (string) ($address['name'] ?? $order['buyer_username'] ?? 'Pembeli Shopee'),
            buyerPhone: $address['phone'] ?? null,
            buyerEmail: null,
            buyerCity: $address['city'] ?? null,
            buyerAddress: $address['full_address'] ?? null,
            totalAmount: (int) round((float) ($order['total_amount'] ?? 0)),
            shippingFee: (int) round((float) ($order['estimated_shipping_fee'] ?? 0)),
            platformFee: 0,
            discountAmount: 0,
            items: $items,
            orderedAt: CarbonImmutable::createFromTimestamp((int) ($order['create_time'] ?? now()->timestamp)),
            raw: $order,
        );
    }

    private function url(string $path, int $timestamp, string $sign): string
    {
        return rtrim((string) $this->config['base_url'], '/').$path.'?'.http_build_query([
            'partner_id' => $this->config['partner_id'],
            'timestamp' => $timestamp,
            'sign' => $sign,
        ]);
    }

    /**
     * Base string Shopee: partner_id + path + timestamp, ditambah access_token dan
     * shop_id untuk endpoint yang sudah terotorisasi.
     */
    private function sign(string $path, int $timestamp, ?MarketplaceConnection $connection = null): string
    {
        $base = $this->config['partner_id'].$path.$timestamp;

        if ($connection !== null) {
            $base .= $connection->access_token.$connection->shop_id;
        }

        return hash_hmac('sha256', $base, (string) $this->config['partner_key']);
    }

    private function assertConfigured(): void
    {
        if (! MarketplacePlatform::Shopee->isConfigured()) {
            throw MarketplaceException::notConfigured('Shopee');
        }
    }
}
