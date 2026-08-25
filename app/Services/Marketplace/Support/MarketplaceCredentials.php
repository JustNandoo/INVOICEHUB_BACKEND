<?php

namespace App\Services\Marketplace\Support;

use Carbon\CarbonImmutable;

class MarketplaceCredentials
{
    /** @param list<string> $scopes */
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken = null,
        public readonly ?CarbonImmutable $expiresAt = null,
        public readonly ?string $shopId = null,
        public readonly ?string $shopName = null,
        public readonly array $scopes = [],
    ) {}
}
