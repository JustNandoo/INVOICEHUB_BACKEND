<?php

namespace App\Services\Marketplace;

use App\Enums\Marketplace\ConnectionStatus;
use App\Enums\Marketplace\MarketplacePlatform;
use App\Exceptions\Marketplace\MarketplaceException;
use App\Models\MarketplaceConnection;
use App\Models\User;
use App\Services\Subscription\EntitlementService;
use Illuminate\Support\Str;

class MarketplaceConnectionService
{
    public function __construct(
        private readonly MarketplaceProviderFactory $providers,
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Menyiapkan sambungan dan mengembalikan URL izin bila platform memakai OAuth.
     *
     * @return array{connection: MarketplaceConnection, authorizationUrl: string|null}
     */
    public function begin(User $user, MarketplacePlatform $platform, ?string $shopId = null): array
    {
        $this->entitlements->require($user, 'marketplace.integration');

        if (! $platform->isConfigured()) {
            throw MarketplaceException::notConfigured($platform->label());
        }

        $connection = MarketplaceConnection::query()->updateOrCreate(
            ['user_id' => $user->id, 'platform' => $platform->value, 'shop_id' => $shopId],
            [
                'status' => $platform->usesOauth()
                    ? ConnectionStatus::Pending->value
                    : ConnectionStatus::Connected->value,
                'shop_name' => $shopId === null ? $platform->label() : null,
                'state_token' => Str::random(48),
                'state_expires_at' => now()->addMinutes(15),
            ],
        );

        if (! $platform->usesOauth()) {
            return ['connection' => $connection->refresh(), 'authorizationUrl' => null];
        }

        return [
            'connection' => $connection->refresh(),
            'authorizationUrl' => $this->providers->make($platform)->authorizationUrl($connection),
        ];
    }

    /**
     * Menyelesaikan alur OAuth. State wajib cocok dan belum kedaluwarsa, supaya
     * callback dari pihak lain tidak bisa membajak sambungan.
     *
     * @param  array<string, mixed>  $query
     */
    public function complete(string $state, array $query): MarketplaceConnection
    {
        $connection = MarketplaceConnection::query()
            ->where('state_token', $state)
            ->where('state_expires_at', '>', now())
            ->first();

        if (! $connection) {
            throw new MarketplaceException(
                'Tautan otorisasi tidak dikenali atau sudah kedaluwarsa.',
                'MARKETPLACE_INVALID_STATE',
                422,
            );
        }

        try {
            $credentials = $this->providers->make($connection->platform)->exchangeCallback($connection, $query);
        } catch (MarketplaceException $exception) {
            $connection->update([
                'status' => ConnectionStatus::Error->value,
                'last_sync_error' => $exception->getMessage(),
                'state_token' => null,
            ]);

            throw $exception;
        }

        $connection->update([
            'status' => ConnectionStatus::Connected->value,
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'token_expires_at' => $credentials->expiresAt,
            'scopes' => $credentials->scopes,
            'shop_id' => $credentials->shopId ?? $connection->shop_id,
            'shop_name' => $credentials->shopName ?? $connection->shop_name,
            'state_token' => null,
            'state_expires_at' => null,
            'last_sync_error' => null,
        ]);

        return $connection->refresh();
    }

    /** Memutus sambungan dan membuang token. Pesanan yang sudah terimpor tetap ada. */
    public function disconnect(MarketplaceConnection $connection): MarketplaceConnection
    {
        $connection->update([
            'status' => ConnectionStatus::Disconnected->value,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'state_token' => null,
            'state_expires_at' => null,
        ]);

        return $connection->refresh();
    }

    /**
     * Katalog platform beserta status sambungan pengguna, untuk halaman integrasi.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(User $user): array
    {
        $connections = MarketplaceConnection::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (MarketplaceConnection $connection): string => $connection->platform->value);

        $catalog = [];

        foreach (MarketplacePlatform::cases() as $platform) {
            $connection = $connections->get($platform->value);
            $config = $platform->config();

            $catalog[] = [
                'platform' => $platform->value,
                'label' => $platform->label(),
                'description' => $config['description'] ?? null,
                'color' => $config['color'] ?? 'gray',
                'kind' => $config['kind'] ?? 'import',
                'configured' => $platform->isConfigured(),
                'connection' => $connection,
            ];
        }

        return $catalog;
    }
}
