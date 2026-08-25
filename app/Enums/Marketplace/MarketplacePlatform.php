<?php

namespace App\Enums\Marketplace;

enum MarketplacePlatform: string
{
    case Manual = 'manual';
    case Shopee = 'shopee';
    case Tokopedia = 'tokopedia';
    case Lazada = 'lazada';
    case TiktokShop = 'tiktok_shop';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return (array) config("marketplaces.platforms.{$this->value}", []);
    }

    public function label(): string
    {
        return (string) ($this->config()['label'] ?? ucfirst($this->value));
    }

    public function usesOauth(): bool
    {
        return ($this->config()['kind'] ?? 'import') === 'oauth';
    }

    /** Platform OAuth baru bisa dipakai setelah kredensial partner diisi. */
    public function isConfigured(): bool
    {
        if (! $this->usesOauth()) {
            return true;
        }

        $config = $this->config();
        $required = match ($this) {
            self::Shopee => ['partner_id', 'partner_key'],
            self::Tokopedia => ['client_id', 'client_secret', 'fs_id'],
            self::Lazada => ['app_key', 'app_secret'],
            self::TiktokShop => ['app_key', 'app_secret'],
            default => [],
        };

        foreach ($required as $key) {
            if (blank($config[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** Nilai `source` pelanggan yang dipakai InvoiceHub. */
    public function customerSource(): string
    {
        return match ($this) {
            self::Shopee => 'shopee',
            self::Tokopedia => 'tokopedia',
            default => 'other',
        };
    }
}
