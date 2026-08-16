<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ((array) config('subscriptions.plans') as $code => $plan) {
            DB::table('subscription_plans')->updateOrInsert(['code' => $code], [
                'name' => $plan['name'],
                'price' => $plan['price'],
                'billing_interval' => $plan['billing_interval'],
                'description' => $plan['description'],
                'features' => json_encode($plan['features'], JSON_THROW_ON_ERROR),
                'limits' => json_encode($plan['limits'], JSON_THROW_ON_ERROR),
                'sort_order' => $plan['sort_order'],
                'is_recommended' => $plan['is_recommended'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // The catalog table is removed by the preceding schema migration.
    }
};
