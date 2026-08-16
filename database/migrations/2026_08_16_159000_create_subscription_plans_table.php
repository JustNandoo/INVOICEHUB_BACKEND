<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            $table->unsignedBigInteger('price')->default(0);
            $table->string('billing_interval', 20)->nullable();
            $table->string('description', 255);
            $table->json('features');
            $table->json('limits');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_recommended')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
