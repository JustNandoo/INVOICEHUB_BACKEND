<?php

namespace App\Services\Marketplace\Contracts;

use App\Enums\Marketplace\MarketplacePlatform;
use App\Exceptions\Marketplace\MarketplaceException;
use App\Models\MarketplaceConnection;
use App\Services\Marketplace\Support\MarketplaceCredentials;
use App\Services\Marketplace\Support\MarketplaceOrderData;
use Carbon\CarbonImmutable;

interface MarketplaceProvider
{
    public function platform(): MarketplacePlatform;

    /**
     * URL tempat pengguna memberi izin di sisi marketplace.
     *
     * @throws MarketplaceException bila platform tidak memakai OAuth
     */
    public function authorizationUrl(MarketplaceConnection $connection): string;

    /**
     * Menukar parameter callback menjadi token akses.
     *
     * @param  array<string, mixed>  $query
     */
    public function exchangeCallback(MarketplaceConnection $connection, array $query): MarketplaceCredentials;

    /**
     * Mengambil pesanan sejak waktu tertentu.
     *
     * @return list<MarketplaceOrderData>
     */
    public function fetchOrders(MarketplaceConnection $connection, CarbonImmutable $since): array;
}
