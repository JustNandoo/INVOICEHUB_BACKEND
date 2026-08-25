<?php

namespace App\Services\Marketplace\Providers;

use App\Enums\Marketplace\MarketplacePlatform;
use App\Exceptions\Marketplace\MarketplaceException;
use App\Models\MarketplaceConnection;
use App\Services\Marketplace\Contracts\MarketplaceProvider;
use App\Services\Marketplace\Support\MarketplaceCredentials;
use Carbon\CarbonImmutable;

/**
 * Jalur yang bisa dipakai hari ini tanpa persetujuan siapa pun: pengguna mengunduh
 * ekspor pesanan dari Seller Center (Shopee, Tokopedia, Lazada, TikTok Shop) lalu
 * mengunggahnya ke sini. Pesanannya masuk lewat MarketplaceOrderImporter, bukan
 * lewat fetchOrders.
 */
class ManualImportProvider implements MarketplaceProvider
{
    public function platform(): MarketplacePlatform
    {
        return MarketplacePlatform::Manual;
    }

    public function authorizationUrl(MarketplaceConnection $connection): string
    {
        throw MarketplaceException::unsupported($connection->platform->label(), 'otorisasi OAuth');
    }

    public function exchangeCallback(MarketplaceConnection $connection, array $query): MarketplaceCredentials
    {
        throw MarketplaceException::unsupported($connection->platform->label(), 'callback OAuth');
    }

    /** Pesanan datang dari unggahan berkas, bukan dari penarikan berkala. */
    public function fetchOrders(MarketplaceConnection $connection, CarbonImmutable $since): array
    {
        return [];
    }
}
