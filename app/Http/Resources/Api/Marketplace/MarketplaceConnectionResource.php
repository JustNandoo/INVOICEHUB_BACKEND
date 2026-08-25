<?php

namespace App\Http\Resources\Api\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'platformLabel' => $this->platform->label(),
            'shopId' => $this->shop_id,
            'shopName' => $this->shop_name,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'autoSync' => $this->auto_sync,
            'importedOrderCount' => $this->imported_order_count,
            'lastSyncedAt' => $this->last_synced_at?->toIso8601String(),
            'lastSyncError' => $this->last_sync_error,
            'tokenExpiresAt' => $this->token_expires_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
