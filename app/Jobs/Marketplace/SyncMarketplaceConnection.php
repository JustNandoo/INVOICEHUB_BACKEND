<?php

namespace App\Jobs\Marketplace;

use App\Exceptions\Marketplace\MarketplaceException;
use App\Models\MarketplaceConnection;
use App\Services\Marketplace\MarketplaceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMarketplaceConnection implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $connectionId)
    {
        $this->onQueue('reports');
    }

    public function handle(MarketplaceSyncService $sync): void
    {
        $connection = MarketplaceConnection::query()->find($this->connectionId);

        if (! $connection) {
            return;
        }

        try {
            $sync->sync($connection);
        } catch (MarketplaceException $exception) {
            Log::warning('Marketplace sync failed', [
                'connectionId' => $this->connectionId,
                'platform' => $connection->platform->value,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->connectionId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }
}
