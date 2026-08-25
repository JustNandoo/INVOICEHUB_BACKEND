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
 * Lazada Open Platform.
 *
 * Tanda tangan Lazada dibentuk dari path + parameter yang diurutkan menurut nama,
 * lalu di-HMAC-SHA256 dengan app_secret. app_key/app_secret diperoleh setelah
 * aplikasi disetujui di Lazada Open Platform.
 */
class LazadaProvider implements MarketplaceProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function platform(): MarketplacePlatform
    {
        return MarketplacePlatform::Lazada;
    }

    public function authorizationUrl(MarketplaceConnection $connection): string
    {
        $this->assertConfigured();

        return rtrim((string) $this->config['auth_url'], '/').'?'.http_build_query([
            'response_type' => 'code',
            'force_auth' => 'true',
            'client_id' => $this->config['app_key'],
            'redirect_uri' => config('marketplaces.redirect_url'),
            'state' => $connection->state_token,
        ]);
    }

    public function exchangeCallback(MarketplaceConnection $connection, array $query): MarketplaceCredentials
    {
        $this->assertConfigured();

        $code = (string) ($query['code'] ?? '');

        if ($code === '') {
            throw new MarketplaceException('Callback Lazada tidak menyertakan code.', 'MARKETPLACE_BAD_CALLBACK');
        }

        $path = '/auth/token/create';
        $params = $this->baseParams(['code' => $code]);
        $params['sign'] = $this->sign($path, $params);

        $response = Http::timeout(30)->asForm()->post((string) $this->config['token_url'], $params);

        if ($response->failed() || $response->json('code') !== '0') {
            throw new MarketplaceException('Lazada menolak penukaran token: '.$response->body(), 'MARKETPLACE_TOKEN_FAILED');
        }

        $body = $response->json();
        $shop = $body['country_user_info'][0] ?? [];

        return new MarketplaceCredentials(
            accessToken: (string) ($body['access_token'] ?? ''),
            refreshToken: $body['refresh_token'] ?? null,
            expiresAt: isset($body['expires_in'])
                ? CarbonImmutable::now()->addSeconds((int) $body['expires_in'])
                : null,
            shopId: $shop['seller_id'] ?? ($body['account'] ?? null),
            shopName: $body['account'] ?? null,
        );
    }

    public function fetchOrders(MarketplaceConnection $connection, CarbonImmutable $since): array
    {
        $this->assertConfigured();

        $path = '/orders/get';
        $params = $this->baseParams([
            'access_token' => $connection->access_token,
            'created_after' => $since->toIso8601String(),
            'limit' => 50,
            'sort_direction' => 'DESC',
        ]);
        $params['sign'] = $this->sign($path, $params);

        $response = Http::timeout(45)->get(rtrim((string) $this->config['base_url'], '/').$path, $params);

        if ($response->failed()) {
            throw new MarketplaceException('Gagal mengambil pesanan Lazada: '.$response->body(), 'MARKETPLACE_FETCH_FAILED');
        }

        $orders = [];

        foreach ($response->json('data.orders') ?? [] as $order) {
            $orders[] = new MarketplaceOrderData(
                externalOrderId: (string) ($order['order_id'] ?? ''),
                orderNumber: isset($order['order_number']) ? (string) $order['order_number'] : null,
                status: (string) (($order['statuses'][0] ?? null) ?? 'unknown'),
                buyerName: trim(($order['address_shipping']['first_name'] ?? '').' '.($order['address_shipping']['last_name'] ?? '')) ?: 'Pembeli Lazada',
                buyerPhone: $order['address_shipping']['phone'] ?? null,
                buyerEmail: $order['customer_email'] ?? null,
                buyerCity: $order['address_shipping']['city'] ?? null,
                buyerAddress: $order['address_shipping']['address1'] ?? null,
                totalAmount: (int) round((float) ($order['price'] ?? 0)),
                shippingFee: (int) round((float) ($order['shipping_fee'] ?? 0)),
                platformFee: 0,
                discountAmount: (int) round((float) ($order['voucher'] ?? 0)),
                items: [],
                orderedAt: CarbonImmutable::parse($order['created_at'] ?? now()),
                raw: $order,
            );
        }

        return $orders;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function baseParams(array $extra = []): array
    {
        return array_merge([
            'app_key' => $this->config['app_key'],
            'timestamp' => (string) (now()->timestamp * 1000),
            'sign_method' => 'sha256',
        ], $extra);
    }

    /** @param array<string, mixed> $params */
    private function sign(string $path, array $params): string
    {
        ksort($params);
        $base = $path;

        foreach ($params as $key => $value) {
            $base .= $key.$value;
        }

        return strtoupper(hash_hmac('sha256', $base, (string) $this->config['app_secret']));
    }

    private function assertConfigured(): void
    {
        if (! MarketplacePlatform::Lazada->isConfigured()) {
            throw MarketplaceException::notConfigured('Lazada');
        }
    }
}
