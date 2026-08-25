<?php

namespace App\Services\Marketplace;

use App\Enums\Marketplace\MarketplacePlatform;
use App\Services\Marketplace\Contracts\MarketplaceProvider;
use App\Services\Marketplace\Providers\LazadaProvider;
use App\Services\Marketplace\Providers\ManualImportProvider;
use App\Services\Marketplace\Providers\ShopeeProvider;
use App\Services\Marketplace\Providers\TokopediaProvider;

class MarketplaceProviderFactory
{
    public function make(MarketplacePlatform $platform): MarketplaceProvider
    {
        $config = $platform->config();

        return match ($platform) {
            MarketplacePlatform::Shopee => new ShopeeProvider($config),
            MarketplacePlatform::Lazada => new LazadaProvider($config),
            MarketplacePlatform::Tokopedia => new TokopediaProvider($config),
            // TikTok Shop belum punya adapter khusus; sementara lewat impor manual.
            default => new ManualImportProvider,
        };
    }
}
