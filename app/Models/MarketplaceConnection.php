<?php

namespace App\Models;

use App\Enums\Marketplace\ConnectionStatus;
use App\Enums\Marketplace\MarketplacePlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceConnection extends Model
{
    protected $fillable = [
        'user_id', 'platform', 'shop_id', 'shop_name', 'status',
        'access_token', 'refresh_token', 'token_expires_at', 'scopes',
        'state_token', 'state_expires_at', 'auto_sync',
        'last_synced_at', 'synced_until', 'last_sync_error', 'imported_order_count',
    ];

    protected $hidden = ['access_token', 'refresh_token', 'state_token'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class);
    }

    /** @param Builder<MarketplaceConnection> $query */
    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', ConnectionStatus::Connected->value);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    protected function casts(): array
    {
        return [
            'platform' => MarketplacePlatform::class,
            'status' => ConnectionStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'scopes' => 'array',
            'auto_sync' => 'boolean',
            'imported_order_count' => 'integer',
            'token_expires_at' => 'immutable_datetime',
            'state_expires_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
            'synced_until' => 'immutable_datetime',
        ];
    }
}
