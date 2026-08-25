<?php

namespace App\Jobs\Marketplace;

use App\Enums\Marketplace\ConnectionStatus;
use App\Models\MarketplaceConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fan-out sinkronisasi berkala. Hanya sambungan OAuth yang ditarik otomatis;
 * impor manual menunggu unggahan pengguna.
 */
class DispatchMarketplaceSync implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        if (! config('marketplaces.enabled')) {
            return;
        }

        MarketplaceConnection::query()
            ->where('status', ConnectionStatus::Connected->value)
            ->where('auto_sync', true)
            ->orderBy('id')
            ->chunkById(100, function ($connections): void {
                foreach ($connections as $connection) {
                    if (! $connection->platform->usesOauth()) {
                        continue;
                    }

                    SyncMarketplaceConnection::dispatch($connection->id);
                }
            });
    }

    public function uniqueId(): string
    {
        return now()->format('Y-m-d-H');
    }
}
