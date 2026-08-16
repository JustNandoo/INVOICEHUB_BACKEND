<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'code', 'name', 'price', 'billing_interval', 'description', 'features', 'limits',
        'sort_order', 'is_recommended', 'is_active',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    public function limit(string $key): ?int
    {
        $value = data_get($this->limits, $key);

        return $value === null ? null : (int) $value;
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer', 'features' => 'array', 'limits' => 'array',
            'sort_order' => 'integer', 'is_recommended' => 'boolean', 'is_active' => 'boolean',
        ];
    }
}
