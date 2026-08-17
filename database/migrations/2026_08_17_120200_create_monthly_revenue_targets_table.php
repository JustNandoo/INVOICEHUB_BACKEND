<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_revenue_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('amount');
            $table->timestampTz('reached_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'year', 'month'], 'revenue_targets_owner_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_revenue_targets');
    }
};
