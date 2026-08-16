<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('source', 30)->default('default');
            $table->timestampTz('starts_at');
            $table->timestampTz('current_period_starts_at');
            $table->timestampTz('current_period_ends_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('provider_customer_id', 120)->nullable();
            $table->string('provider_subscription_id', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'current_period_ends_at']);
            $table->unique(['provider', 'provider_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
