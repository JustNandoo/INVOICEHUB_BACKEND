<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds the anomaly.* and ai.anomaly_explanation keys to the stored plan catalog.
     * Existing plan values are untouched.
     */
    public function up(): void
    {
        foreach ((array) config('subscriptions.plans') as $code => $plan) {
            DB::table('subscription_plans')->where('code', $code)->update([
                'features' => json_encode($plan['features'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ((array) config('subscriptions.plans') as $code => $plan) {
            $features = collect($plan['features'])
                ->reject(fn ($value, string $key): bool => $key === 'anomaly.detection' || $key === 'ai.anomaly_explanation')
                ->all();

            DB::table('subscription_plans')->where('code', $code)->update([
                'features' => json_encode($features, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }
};
