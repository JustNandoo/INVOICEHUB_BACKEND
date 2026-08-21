<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Re-sync the plan catalog so the new ai.* features and aiCredits* limits reach
     * the database. Existing plan values are unchanged; only new keys are added.
     */
    public function up(): void
    {
        foreach ((array) config('subscriptions.plans') as $code => $plan) {
            DB::table('subscription_plans')->where('code', $code)->update([
                'features' => json_encode($plan['features'], JSON_THROW_ON_ERROR),
                'limits' => json_encode($plan['limits'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ((array) config('subscriptions.plans') as $code => $plan) {
            $features = collect($plan['features'])->reject(fn ($value, string $key): bool => str_starts_with($key, 'ai.'))->all();
            $limits = collect($plan['limits'])->reject(fn ($value, string $key): bool => str_starts_with($key, 'ai'))->all();

            DB::table('subscription_plans')->where('code', $code)->update([
                'features' => json_encode($features, JSON_THROW_ON_ERROR),
                'limits' => json_encode($limits, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }
};
